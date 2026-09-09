<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\IntoClauseRule;

#[CoversClass(IntoClauseRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class IntoClauseRuleTest extends TestCase
{
    public function testRewriteRemovesOnlySubqueryDestinations(): void
    {
        $inner = new TerminalOccurrence('INTO', 4, [0, 1, 2], ['stmt', 'subquery', 'into_clause']);
        $outer = new TerminalOccurrence('INTO', 5, [0, 3], ['stmt', 'into_clause']);
        $input = new TerminalSequence([$inner, $outer], [$inner, $outer], [], [new ProductionOccurrence(2, 1, 'into_clause', 0), new ProductionOccurrence(3, 0, 'into_clause', 0), new ProductionOccurrence(6, 0, 'into_clause', 0)]);
        $rule = new IntoClauseRule();
        $result = $rule->rewrite($input);
        self::assertSame([$outer], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }
}
