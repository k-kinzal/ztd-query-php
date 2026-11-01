<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\ValueRule;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\BareName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment;

#[CoversClass(ValueRule::class)]
#[Medium]
final class ValueRuleTest extends TestCase
{
    public function testValueLowersTheKeywords(): void
    {
        self::assertSame('SET autocommit = ON, sql_mode = ALL, character_set_client = BINARY', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('set autocommit = on, sql_mode = all, character_set_client = binary')->toString());
        self::assertSame('SET a = ROW, b = SYSTEM', (new Semantics(Dialect::MySql))->analyze('set a = row, b = system')->toString());
    }

    public function testWordLowersABareName(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET @@autocommit = b, @@sql_mode = (d)');
        self::assertInstanceOf(SetVariables::class, $set);
        self::assertInstanceOf(SystemAssignment::class, $set->items[1]);
        self::assertNotInstanceOf(BareName::class, $set->items[1]->value);
    }
    public function testOperandPreservesAStoredRowValue(): void
    {
        self::assertSame('CREATE PROCEDURE p(x INT) SET x = (1, 2)', (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p(x INT) SET x=(1,2)')->toString());
    }
}
