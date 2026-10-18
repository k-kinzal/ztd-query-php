<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver;

use Deriver\Deriver\Candidate\Alternative;
use Deriver\Deriver\Candidate as Subject;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class CandidateTest extends TestCase
{
    public function testClassifiesConcreteNullAsAnalyzed(): void
    {
        $root = new Node(EvidenceKind::SourceDefinition);
        $proof = new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        $candidate = new Subject(Term::constant(null), [$proof]);
        self::assertSame(CandidateStatus::Analyzed, $candidate->type);
        self::assertSame('null', $candidate->type_name);
        self::assertNull($candidate->result);
    }
    public function testClassifiesResidualExpressionsWithoutGuessingAValue(): void
    {
        $root = new Node(EvidenceKind::Unexpanded);
        $proof = new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        $term = new Term(TermKind::Concat, operands: [Term::constant('prefix'), Term::parameter('x')]);
        $candidate = new Subject($term, [$proof]);
        self::assertSame(CandidateStatus::Partials, $candidate->type);
        self::assertSame('string', $candidate->type_name);
        self::assertSame($term, $candidate->result);
    }
    public function testExposesConcreteValuesThroughResultWithoutAPublicTerm(): void
    {
        $root = new Node(EvidenceKind::SourceDefinition);
        $candidate = new Subject(Term::fromNative(['answer' => 42, 'nothing' => null]), [new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')))]);
        self::assertSame(['answer' => 42, 'nothing' => null], $candidate->result);
        self::assertSame(['type', 'type_name', 'result', 'evidence'], array_keys(get_object_vars($candidate)));
    }
    public function testWithEvidencePreservesTheValueAndLeavesTheOriginalUnchanged(): void
    {
        $root = new Node(EvidenceKind::SourceDefinition);
        $proof = new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        $root1 = new Node(EvidenceKind::EvaluatorApplication, attributes: ['id' => 'example.replacement']);
        $replacement = new Alternative($root1, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        $term = Term::parameter('input');
        $candidate = new Subject($term, [$proof]);
        $copy = $candidate->withEvidence([$replacement]);
        self::assertNotSame($candidate, $copy);
        self::assertSame($candidate->type, $copy->type);
        self::assertSame($candidate->type_name, $copy->type_name);
        self::assertSame($term, $copy->result);
        self::assertSame([$replacement], $copy->evidence);
        self::assertSame([$proof], $candidate->evidence);
    }
    public function testPreservesApplicationClassNamesAsTypeNames(): void
    {
        $root = new Node(EvidenceKind::Unexpanded);
        $proof = new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        $candidate = new Subject(Term::parameter('repository', 'App\Repository'), [$proof]);
        self::assertSame(CandidateStatus::Partials, $candidate->type);
        self::assertSame('App\Repository', $candidate->type_name);
    }
    public function testPreservesUnionsContainingApplicationClasses(): void
    {
        $root = new Node(EvidenceKind::Unexpanded);
        $proof = new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        $term = Term::parameter('source', 'App\Repository|App\Cache|null');
        $candidate = new Subject($term, [$proof]);
        self::assertSame('App\Repository|App\Cache|null', $candidate->type_name);
        self::assertSame($term, $candidate->result);
    }
}
