<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\FactorRule;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;

#[CoversClass(FactorRule::class)]
#[Medium]
final class FactorRuleTest extends TestCase
{
    public function testFactorRejectsASelectOutsideParentheses(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM t, SELECT 1');
    }

    public function testParensLowersDerivedTablesAndNestedJoins(): void
    {
        self::assertSame('SELECT 1 FROM (SELECT 1) d, (t, u), ((SELECT 2)) e', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select 1 from (select 1) d, (t, u), ((select 2)) e')->toString());
        self::assertSame('SELECT 1 FROM (SELECT 1 UNION SELECT 2 ORDER BY 1) d', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from (select 1 union select 2 order by 1) d')->toString());
    }

    public function testParensRejectsAnAliasOnANestedJoin(): void
    {
        $this->expectException(AnalysisException::class);

        (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT 1 FROM (t, u) AS x');
    }

    public function testContentAnswersTheBlockOfADerivedTable(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SELECT 1 FROM ((SELECT 1)) AS d');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(DerivedTable::class, $operation->statement->from);
        self::assertInstanceOf(ParenthesizedQuery::class, $operation->statement->from->query);
    }

    public function testSingleAnswersOnlyALoneParenthesizedOrSelectFactor(): void
    {
        self::assertSame('SELECT 1 FROM ((t))', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 from ((t))')->toString());
    }
}
