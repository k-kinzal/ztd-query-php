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
use Deriver\Reporter\TextReporter;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TextReporterTest extends TestCase
{
    public function testReportKeepsNativeLiteralTypesVisible(): void
    {
        $node = new Node(EvidenceKind::Observation);
        $evidence = [new Alternative($node, $node, new Snapshot('s', [], [], new \Deriver\Deriver\Evaluator\TargetProfile(), 'environment'))];
        $candidates = new CandidateCollection([new Candidate(Term::fromNative(['id' => 7, 'enabled' => false]), $evidence)], new ResultRef('r'));
        $text = (new TextReporter())->report($candidates);
        self::assertStringContainsString('[0] analyzed array', $text);
        self::assertStringContainsString("'enabled' => false", $text);
        self::assertStringContainsString('Evidence: 1 derivation(s).', $text);
    }

    public function testReportDistinguishesPartialsAndInterruption(): void
    {
        $node = new Node(EvidenceKind::Observation);
        $evidence = [new Alternative($node, $node, new Snapshot('s', [], [], new \Deriver\Deriver\Evaluator\TargetProfile(), 'environment'))];
        $candidates = new CandidateCollection([new Candidate(Term::parameter('id', 'int'), $evidence)], new ResultRef('r'), interrupted: true);
        $text = (new TextReporter())->report($candidates);
        self::assertStringContainsString('[0] partials int', $text);
        self::assertStringContainsString("'literal' => 'id'", $text);
        self::assertStringContainsString('Exploration interrupted', $text);
    }

    public function testReportDescribesAnEmptyCollection(): void
    {
        self::assertSame("No candidates.\n", (new TextReporter())->report(new CandidateCollection([], new ResultRef('r'))));
    }
}
