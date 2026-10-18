<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter;

use Deriver\Deriver\Candidate;
use Deriver\Deriver\Candidate\Alternative;
use Deriver\Deriver\Candidate\CandidateCollection;
use Deriver\Deriver\Candidate\ResultRef;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Exception\ReportingException;
use Deriver\Reporter\JsonReporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class JsonReporterTest extends TestCase
{
    public function testReportIncludesConcreteValuesAndOrderedEvidence(): void
    {
        $input = new Node(EvidenceKind::Operation);
        $node = new Node(EvidenceKind::Observation, ['value' => $input]);
        $evidence = [new Alternative($node, $node, new Snapshot('s', [], [], new \Deriver\Deriver\Evaluator\TargetProfile(), 'environment'))];
        $candidates = new CandidateCollection([new Candidate(Term::constant(1.0), $evidence)], new ResultRef('r'));
        $text = (new JsonReporter())->report($candidates);
        self::assertStringContainsString('"result": 1.0', $text);
        self::assertStringContainsString('"interrupted": false', $text);
        self::assertStringContainsString($node->id, $text);
        self::assertStringContainsString($input->id, $text);
        self::assertStringNotContainsString('sourceModes', $text);
    }

    public function testReportIncludesResidualStructure(): void
    {
        $node = new Node(EvidenceKind::Observation);
        $evidence = [new Alternative($node, $node, new Snapshot('s', [], [], new \Deriver\Deriver\Evaluator\TargetProfile(), 'environment'))];
        $candidates = new CandidateCollection([new Candidate(Term::parameter('id', 'int'), $evidence)], new ResultRef('r'), interrupted: true);
        $text = (new JsonReporter())->report($candidates);
        self::assertStringContainsString('"type": "partials"', $text);
        self::assertStringContainsString('"literal": "id"', $text);
        self::assertStringContainsString('"interrupted": true', $text);
    }

    public function testReportFailsWithoutSilentlyReplacingBinaryStrings(): void
    {
        $node = new Node(EvidenceKind::Observation);
        $evidence = [new Alternative($node, $node, new Snapshot('s', [], [], new \Deriver\Deriver\Evaluator\TargetProfile(), 'environment'))];
        $candidates = new CandidateCollection([new Candidate(Term::constant("\xff"), $evidence)], new ResultRef('r'));
        $this->expectException(ReportingException::class);
        $this->expectExceptionMessage('Cannot encode report as JSON');
        (new JsonReporter())->report($candidates);
    }

    public function testReportProducesAnEmptyArrayForNoCandidates(): void
    {
        self::assertSame("{\n    \"interrupted\": false,\n    \"candidates\": []\n}\n", (new JsonReporter())->report(new CandidateCollection([], new ResultRef('r'))));
    }
}
