<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Modern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Query\Modern\ReferenceRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ReferenceRule::class)]
#[Medium]
final class ReferenceRuleTest extends TestCase
{
    public function testReferenceLowersEveryReference(): void
    {
        self::assertSame('SELECT 1 FROM { OJ t }, (t JOIN u), t', (new Inspection('mysql-8.4.7'))->inspect('select 1 from { oj t }, (t join u), t')->toString());
    }

    public function testJoinedWalksTheJoinsOnTheLeft(): void
    {
        $operation = (new Inspection())->inspect('SELECT 1 FROM t JOIN u ON 1 JOIN v ON 2 JOIN w ON 3');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(JoinedTable::class, $operation->statement->from);
        self::assertInstanceOf(JoinedTable::class, $operation->statement->from->left);
        self::assertInstanceOf(TableReference::class, $operation->statement->from->right);
    }

    public function testStepLowersEveryJoinForm(): void
    {
        self::assertSame('SELECT 1 FROM t JOIN u USING (a) LEFT JOIN v USING (b) RIGHT JOIN w ON 1 NATURAL LEFT JOIN x', (new Inspection('mysql-8.4.7'))->inspect('select 1 from t join u using (a) left join v using (b) right join w on 1 natural left join x')->toString());
    }

    public function testOperatorLowersEveryOperator(): void
    {
        self::assertSame('SELECT 1 FROM t INNER JOIN u CROSS JOIN v STRAIGHT_JOIN w NATURAL JOIN x NATURAL RIGHT JOIN y', (new Inspection('mysql-8.0.44'))->inspect('select 1 from t inner join u cross join v straight_join w natural join x natural right join y')->toString());
    }
}
