<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\JoinRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;

#[CoversClass(JoinRule::class)]
#[Medium]
final class JoinRuleTest extends TestCase
{
    public function testListLowersTheReferencesInOrder(): void
    {
        self::assertSame('SELECT 1 FROM t, u, v', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from t, u, v')->toString());
    }

    public function testEscapedLowersTheOdbcEscape(): void
    {
        self::assertSame('SELECT 1 FROM { oj t LEFT JOIN u ON 1 }', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from {oj t left join u on 1}')->toString());
    }

    public function testReferenceLowersFactorsAndJoins(): void
    {
        self::assertSame('SELECT 1 FROM (t JOIN u)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from (t join u)')->toString());
    }

    public function testJoinedWalksTheJoinsOnTheLeft(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM t JOIN u JOIN v');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinedTable::class, $operation->statement->from);
        self::assertInstanceOf(JoinedTable::class, $operation->statement->from->left);
    }

    public function testStepLowersEveryJoinForm(): void
    {
        self::assertSame('SELECT 1 FROM t STRAIGHT_JOIN u ON 1 NATURAL JOIN v NATURAL LEFT OUTER JOIN w NATURAL RIGHT JOIN x RIGHT JOIN y USING (a) LEFT OUTER JOIN z ON 1 CROSS JOIN q USING (b)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from t straight_join u on 1 natural join v natural left outer join w natural right join x right join y using (a) left outer join z on 1 cross join q using (b)')->toString());
    }
}
