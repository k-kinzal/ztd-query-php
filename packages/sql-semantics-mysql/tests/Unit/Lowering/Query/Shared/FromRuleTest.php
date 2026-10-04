<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\FromRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

#[CoversClass(FromRule::class)]
#[Medium]
final class FromRuleTest extends TestCase
{
    public function testFromLowersEveryFromClause(): void
    {
        self::assertSame('SELECT 1 FROM DUAL', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from dual')->toString());
        self::assertSame('SELECT 1 FROM t, u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from t, u')->toString());
    }

    public function testTablesAnswersOneReferenceOrAList(): void
    {
        $one = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t');
        $two = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t, u');

        self::assertInstanceOf(Select::class, $one->statement);
        self::assertInstanceOf(Select::class, $two->statement);
        self::assertInstanceOf(TableReference::class, $one->statement->from);
        self::assertInstanceOf(TableList::class, $two->statement->from);
    }

    public function testMembersLowersTheReferencesInOrder(): void
    {
        self::assertSame('SELECT 1 FROM t, u JOIN v ON 1, w', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t, u join v on 1, w')->toString());
    }

    public function testFactorHandsEachGenerationItsFactors(): void
    {
        self::assertSame('SELECT 1 FROM (t)', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from (t)')->toString());
        self::assertSame('SELECT 1 FROM (t)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from (t)')->toString());
    }
}
