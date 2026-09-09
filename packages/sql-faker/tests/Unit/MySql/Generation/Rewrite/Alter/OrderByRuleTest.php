<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule;

#[CoversClass(OrderByRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class OrderByRuleTest extends TestCase
{
    public function testRewriteMovesTheCompleteOrderListAfterFollowingActions(): void
    {
        $order = new TerminalOccurrence('ORDER_SYM', 10, [0, 1, 2], ['alter_list', 'alter_list', 'alter_list_item']);
        $by = new TerminalOccurrence('BY', 11, [0, 1, 2], ['alter_list', 'alter_list', 'alter_list_item']);
        $a = new TerminalOccurrence('A', 12, [0, 1, 2, 3], ['alter_list', 'alter_list', 'alter_list_item', 'alter_order_list']);
        $innerComma = new TerminalOccurrence(',', 13, [0, 1, 2, 3], ['alter_list', 'alter_list', 'alter_list_item', 'alter_order_list']);
        $b = new TerminalOccurrence('B', 14, [0, 1, 2, 3], ['alter_list', 'alter_list', 'alter_list_item', 'alter_order_list']);
        $comma = new TerminalOccurrence(',', 15, [0], ['alter_list']);
        $enable = new TerminalOccurrence('ENABLE_SYM', 16, [0, 4], ['alter_list', 'alter_list_item']);
        $keys = new TerminalOccurrence('KEYS', 17, [0, 4], ['alter_list', 'alter_list_item']);
        $outside = new TerminalOccurrence('OTHER', 18, [9], ['other_statement']);
        $terminals = [$order, $by, $a, $innerComma, $b, $comma, $enable, $keys, $outside];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'alter_list', 1), new ProductionOccurrence(1, 0, 'alter_list', 0),
            new ProductionOccurrence(2, 1, 'alter_list_item', 0), new ProductionOccurrence(3, 2, 'alter_order_list', 1),
            new ProductionOccurrence(4, 0, 'alter_list_item', 0), new ProductionOccurrence(9, null, 'other_statement', 0),
        ]);
        $rule = new OrderByRule();
        $result = $rule->rewrite($input);
        self::assertSame([$enable, $keys, $comma, $order, $by, $a, $innerComma, $b, $outside], $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($input->productions, $result->productions);
        self::assertSame(['sql_yacc.yy:alter_list:order-by-last'], $result->rewrites);
        self::assertSame($result, $rule->rewrite($result));
    }

    public function testReorderPreservesAnOrderByThatAlreadyEndsItsOwnStatement(): void
    {
        $order = new TerminalOccurrence('ORDER_SYM', 2, [0, 1], ['alter_list', 'alter_list_item']);
        $outside = new TerminalOccurrence('ENABLE_SYM', 3, [4, 5], ['alter_list', 'alter_list_item']);
        $input = new TerminalSequence([$order, $outside], [], [], [
            new ProductionOccurrence(0, null, 'alter_list', 0), new ProductionOccurrence(1, 0, 'alter_list_item', 0),
            new ProductionOccurrence(4, null, 'alter_list', 0), new ProductionOccurrence(5, 4, 'alter_list_item', 0),
        ]);
        self::assertSame($input, (new OrderByRule())->rewrite($input));
        self::assertSame($input, (new OrderByRule())->reorder($input, 99));
    }

    public function testRewritePreservesUnrelatedOrderByTokens(): void
    {
        $input = TerminalSequence::fromNames(['ORDER_SYM', 'BY', 'IDENT', ',', 'ENABLE_SYM', 'KEYS']);
        self::assertSame($input, (new OrderByRule())->rewrite($input));
    }
}
