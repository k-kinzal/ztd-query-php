<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableRule;

#[CoversClass(ParseTable::class)]
#[UsesClass(ActionCode::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(TableRule::class)]
#[Small]
final class ParseTableTest extends TestCase
{
    public function testActionPrefersTheExplicitEntry(): void
    {
        $table = new ParseTable(new SymbolTable(['$end', 'ID', 'ABORT', 'ANY'], ['$accept', 's']), [new TableRule(4, 2, 0)], [ActionCode::reduce(0)], new ArrayRows([[1 => 7, 4 => 9]]));

        self::assertSame(7, $table->action(0, 1));
        self::assertSame(9, $table->action(0, 4));
    }

    public function testActionFallsBackThenUsesTheWildcardThenTheDefault(): void
    {
        $symbols = new SymbolTable(['$end', 'ID', 'ABORT', 'ANY', 'X'], ['$accept', 's']);
        $table = new ParseTable($symbols, [new TableRule(5, 1, 0)], [ActionCode::reduce(0), ActionCode::ERROR], new ArrayRows([[1 => 7, 3 => 8], []]), [2 => 1], 3);

        self::assertSame(7, $table->action(0, 2));
        self::assertSame(8, $table->action(0, 4));
        self::assertSame(ActionCode::reduce(0), $table->action(0, 0));
        self::assertSame(ActionCode::ERROR, $table->action(1, 4));
        self::assertSame(ActionCode::ERROR, $table->action(5, 4));
    }

    public function testExpectedTerminals(): void
    {
        $symbols = new SymbolTable(['$end', 'ID', 'ABORT'], ['$accept', 's']);
        $table = new ParseTable($symbols, [], [ActionCode::ERROR], new ArrayRows([[2 => 4, 1 => ActionCode::ERROR, 4 => 3]]));

        self::assertSame([2], $table->expectedTerminals(0));
    }

    public function testStateCount(): void
    {
        $table = new ParseTable(new SymbolTable(['$end'], ['$accept']), [], [ActionCode::ERROR, ActionCode::ERROR], new ArrayRows([[], []]));

        self::assertSame(2, $table->stateCount());
    }
}
