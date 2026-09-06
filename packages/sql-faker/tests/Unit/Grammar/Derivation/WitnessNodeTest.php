<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\WitnessNode;

#[CoversClass(WitnessNode::class)]
final class WitnessNodeTest extends TestCase
{
    public function testSequencePreservesLeftmostExpansionOrderAndAllSiblings(): void
    {
        $leaf = new WitnessNode('expr', 0, [], 1, 1);
        $sum = new WitnessNode('expr', 1, [$leaf, $leaf], 3, 3);
        $root = new WitnessNode('stmt', 0, [$sum, new WitnessNode('tail', 0, [], 1, 0)], 5, 3);
        self::assertSame([['stmt', 0], ['expr', 1], ['expr', 0], ['expr', 0], ['tail', 0]], $root->sequence());
        self::assertSame(["stmt\0" . 0 => true, "expr\0" . 1 => true, "expr\0" . 0 => true, "tail\0" . 0 => true], $root->contains);
        self::assertSame(5, $root->cost);
        self::assertSame(3, $root->state);
        self::assertSame([$sum, $root->children[1]], $root->children);
    }
}
