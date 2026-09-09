<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Name;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Name\RelationNameRule;

#[CoversClass(RelationNameRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class RelationNameRuleTest extends TestCase
{
    public function testRewriteRemovesRelationStarsButPreservesExpressionIndirection(): void
    {
        $star = new TerminalOccurrence('.', 3, [0, 1, 2], ['qualified_name', 'indirection', 'indirection_el']);
        $suffix = new TerminalOccurrence('*', 4, [0, 1, 2], ['qualified_name', 'indirection', 'indirection_el']);
        $expression = new TerminalOccurrence('[', 9, [5, 6, 7, 8], ['qualified_name', 'expression', 'indirection', 'indirection_el']);
        $input = new TerminalSequence([$star, $suffix, $expression], [], [], [
            new ProductionOccurrence(2, 1, 'indirection_el', 1), new ProductionOccurrence(8, 7, 'indirection_el', 2),
        ]);
        self::assertSame([$expression], (new RelationNameRule())->rewrite($input)->terminals);
    }
}
