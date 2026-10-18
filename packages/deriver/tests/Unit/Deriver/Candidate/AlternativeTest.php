<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Candidate;

use Deriver\Deriver\Candidate\Alternative;
use Deriver\Deriver\Candidate\Snapshot;
use Deriver\Deriver\Evaluator\Evidence\EvidenceKind;
use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\TargetProfile;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class AlternativeTest extends TestCase
{
    public function testNodesFollowsTheDerivationTreeInDependencyOrder(): void
    {
        $input = new Node(EvidenceKind::ExternalInput, attributes: ['input' => 'role']);
        $condition = new Node(EvidenceKind::Choice, ['condition' => $input]);
        $literal = new Node(EvidenceKind::SourceDefinition, attributes: ['definition' => 'selected return']);
        $root = new Node(EvidenceKind::Observation, ['condition' => $condition, 'value' => $literal]);
        $context = new Node(EvidenceKind::ContextAny);
        $proof = new Alternative($root, $context, new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));

        self::assertSame([$root, $condition, $input, $literal], $proof->nodes());
    }

    public function testNodesResolvesSharedEdges(): void
    {
        $leaf = new Node(EvidenceKind::ContextAny);
        $root = new Node(EvidenceKind::Operation, ['left' => $leaf, 'right' => $leaf]);
        $proof = new Alternative($root, $leaf, new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]')));
        self::assertCount(2, $proof->nodes());
    }
    public function testMatchesCallerRetainsUnconstrainedOrigins(): void
    {
        $root = new Node(EvidenceKind::SourceDefinition);
        self::assertTrue((new Alternative($root, new Node(EvidenceKind::ContextAny), new Snapshot('s', [], [], new TargetProfile(), hash('sha256', '[]'))))->matchesCaller('any'));
    }
}
