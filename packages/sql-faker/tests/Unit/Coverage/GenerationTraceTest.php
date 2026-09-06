<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GenerationTrace;

#[CoversClass(GenerationTrace::class)]
final class GenerationTraceTest extends TestCase
{
    public function testBeginAttemptStartsWithNoSelections(): void
    {
        $trace = new GenerationTrace(9, 'stmt', []);
        $trace->beginAttempt(0);
        self::assertSame([], $trace->value['attempts'][0]['events']);
        self::assertSame('in-progress', $trace->value['status']);
    }

    public function testRecordDistinguishesSiblingOccurrencesWithTheSameRule(): void
    {
        $trace = new GenerationTrace(9, 'expr', []);
        $trace->beginAttempt(0);
        $trace->record(0, null, null, 'expr', 'recursive', 'input');
        $trace->record(1, 0, 0, 'expr', 'literal', 'budget');
        $trace->record(3, 0, 2, 'expr', 'literal', 'budget');
        self::assertSame([0, 1, 3], array_column($trace->value['attempts'][0]['events'], 'nodeId'));
        self::assertSame([null, 0, 2], array_column($trace->value['attempts'][0]['events'], 'rhsPosition'));
        self::assertSame(['recursive', 'literal'], $trace->value['reachedIds']);
    }

    public function testDiscardRetainsFailedSelectionsAndTheirError(): void
    {
        $trace = new GenerationTrace(1, 'stmt', []);
        $trace->beginAttempt(0);
        $trace->record(0, null, null, 'stmt', 'p0', 'input');
        $trace->discard('bad lexical token');
        self::assertSame(['p0'], $trace->value['reachedIds']);
        self::assertSame([], $trace->value['emittedIds']);
        self::assertSame('bad lexical token', $trace->value['attempts'][0]['error']);
    }

    public function testCommitIncludesOnlyTheSuccessfulAttempt(): void
    {
        $trace = new GenerationTrace(1, 'stmt', []);
        $trace->beginAttempt(0);
        $trace->record(0, null, null, 'stmt', 'failed', 'input');
        $trace->discard('retry');
        $trace->beginAttempt(1);
        $trace->record(0, null, null, 'stmt', 'success', 'input');
        self::assertSame(['success'], $trace->commit('hash'));
        self::assertSame(['failed', 'success'], $trace->value['reachedIds']);
        self::assertSame('hash', $trace->value['attempts'][1]['sqlHash']);
    }

    public function testEndFinalizesAnInterruptedGeneration(): void
    {
        $trace = new GenerationTrace(1, 'stmt', []);
        $trace->beginAttempt(0);
        $trace->end();
        self::assertSame('failed', $trace->value['status']);
        self::assertSame('discarded', $trace->value['attempts'][0]['status']);
    }

    public function testTraceAndEventsRetainThePublicDiagnosticIdentity(): void
    {
        $plan = ['budget' => 12, 'lexicalTarget' => 'identifier'];
        $trace = new GenerationTrace(42, 'expr', $plan);
        self::assertSame(['generationId' => 42, 'root' => 'expr', 'planSummary' => $plan,
            'attempts' => [], 'lexicalEvents' => ['identifier'], 'status' => 'in-progress',
            'reachedIds' => [], 'emittedIds' => []], $trace->value);
        $trace->beginAttempt(7);
        self::assertSame(['id' => 7, 'events' => [], 'status' => 'in-progress', 'error' => null, 'sqlHash' => null], $trace->value['attempts'][0]);
        $trace->record(12, 9, 3, 'term', 'term#2', 'budget');
        self::assertSame(['generationId' => 42, 'attemptId' => 7, 'nodeId' => 12,
            'parentNodeId' => 9, 'rhsPosition' => 3, 'ruleId' => 'term',
            'productionId' => 'term#2', 'selectionReason' => 'budget'], $trace->value['attempts'][0]['events'][0]);
    }

    public function testCommittedTraceSurvivesEndAndDeduplicatesRepeatedProductions(): void
    {
        $trace = new GenerationTrace(1, 'stmt', []);
        $trace->beginAttempt(0);
        $trace->record(0, null, null, 'stmt', 'p0', 'input');
        $trace->record(1, 0, 0, 'stmt', 'p0', 'input');
        self::assertSame(['p0'], $trace->commit('sql'));
        $trace->end();
        self::assertSame('success', $trace->value['status']);
        self::assertSame('committed', $trace->value['attempts'][0]['status']);
        self::assertNull($trace->value['attempts'][0]['error']);
        self::assertSame(['p0'], $trace->value['emittedIds']);
    }

    public function testEndingBeforeAnyAttemptDoesNotInventAnAttemptOrProduction(): void
    {
        $trace = new GenerationTrace(1, 'stmt', ['lexicalTarget' => null]);
        $trace->discard('before start');
        self::assertSame([], $trace->commit('none'));
        $trace->end();
        self::assertSame([], $trace->value['attempts']);
        self::assertSame([], $trace->value['lexicalEvents']);
        self::assertSame('failed', $trace->value['status']);
    }

}
