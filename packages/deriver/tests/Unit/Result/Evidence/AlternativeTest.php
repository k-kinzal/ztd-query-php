<?php

declare(strict_types=1);

namespace Tests\Unit\Result\Evidence;

use Deriver\Result\Evidence\Node;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class AlternativeTest extends TestCase
{
    public function testNodesResolvesSharedEdges(): void
    {
        $leaf = new Node('context-any');
        $root = new Node('operation', ['left' => $leaf,'right' => $leaf]);
        $proof = A::proof($root, $leaf);
        self::assertCount(2, $proof->nodes());
    }

    public function testMatchesCallerRetainsUnconstrainedOrigins(): void
    {
        self::assertTrue(A::proof(new Node('source-definition'))->matchesCaller('any'));
    }

    public function testToArrayRetainsSnapshotAndRoots(): void
    {
        $proof = A::proof(new Node('source-definition'));
        $record = $proof->toArray();
        self::assertSame($proof->root->id, $record['root']);
        self::assertSame($proof->snapshot, $record['snapshot']);
    }

}
