<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Modern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ReferenceRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

#[CoversClass(ReferenceRule::class)]
#[Medium]
final class ReferenceRuleTest extends TestCase
{
    public function testReferenceLowersEveryReference(): void
    {
        self::assertSame('SELECT 1 FROM { OJ t }, (t JOIN u), t', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from { oj t }, (t join u), t')->toString());
    }

    public function testJoinedWalksTheJoinsOnTheLeft(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 FROM t JOIN u ON 1 JOIN v ON 2 JOIN w ON 3');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinedTable::class, $operation->statement->from);
        self::assertInstanceOf(JoinedTable::class, $operation->statement->from->left);
        self::assertInstanceOf(TableReference::class, $operation->statement->from->right);
    }

    public function testStepLowersEveryJoinForm(): void
    {
        self::assertSame('SELECT 1 FROM t JOIN u USING (a) LEFT JOIN v USING (b) RIGHT JOIN w ON 1 NATURAL LEFT JOIN x', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select 1 from t join u using (a) left join v using (b) right join w on 1 natural left join x')->toString());
    }

    public function testOperatorLowersEveryOperator(): void
    {
        self::assertSame('SELECT 1 FROM t INNER JOIN u CROSS JOIN v STRAIGHT_JOIN w NATURAL JOIN x NATURAL RIGHT JOIN y', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('select 1 from t inner join u cross join v straight_join w natural join x natural right join y')->toString());
    }
}
