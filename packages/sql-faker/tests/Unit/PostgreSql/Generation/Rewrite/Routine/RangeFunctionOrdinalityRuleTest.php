<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\RangeFunctionOrdinalityRule;

#[CoversClass(RangeFunctionOrdinalityRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class RangeFunctionOrdinalityRuleTest extends TestCase
{
    #[DataProvider('providerScopes')]
    public function testRewriteOnlyRemovesOrdinalityBesideItsOwnTypedColumnList(string $function, string $alias, string $columns, int $columnParent, bool $ordinality, bool $remove): void
    {
        $call = new TerminalOccurrence('IDENT', 10, [0, 1], ['table_ref', $function]);
        $with = new TerminalOccurrence('WITH_LA', 11, [0, 1, 2], ['table_ref', $function, 'opt_ordinality']);
        $ordinal = new TerminalOccurrence('ORDINALITY', 12, [0, 1, 2], ['table_ref', $function, 'opt_ordinality']);
        $column = new TerminalOccurrence('IDENT', 13, [0, 3, 4], ['table_ref', $alias, $columns]);
        $terminals = [$call, ...($ordinality ? [$with, $ordinal] : []), $column];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'table_ref', 2),
            new ProductionOccurrence(1, 0, $function, 0),
            new ProductionOccurrence(2, 1, 'opt_ordinality', $ordinality ? 0 : 1),
            new ProductionOccurrence(3, 0, $alias, 2),
            new ProductionOccurrence(4, $columnParent, $columns, 0),
        ]);
        $rule = new RangeFunctionOrdinalityRule();
        $result = $rule->rewrite($input);
        self::assertSame($remove ? [$call, $column] : $terminals, $result->terminals);
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<string, array{string, string, string, int, bool, bool}>
     */
    public static function providerScopes(): iterable
    {
        yield 'typed alias' => ['func_table', 'func_alias_clause', 'TableFuncElementList', 3, true, true];
        yield 'no ordinality' => ['func_table', 'func_alias_clause', 'TableFuncElementList', 3, false, false];
        yield 'plain relation' => ['relation_expr', 'func_alias_clause', 'TableFuncElementList', 3, true, false];
        yield 'other alias scope' => ['func_table', 'opt_alias_clause', 'TableFuncElementList', 3, true, false];
        yield 'column names only' => ['func_table', 'func_alias_clause', 'name_list', 3, true, false];
        yield 'definitions inside rows from' => ['func_table', 'func_alias_clause', 'TableFuncElementList', 1, true, false];
    }
}
