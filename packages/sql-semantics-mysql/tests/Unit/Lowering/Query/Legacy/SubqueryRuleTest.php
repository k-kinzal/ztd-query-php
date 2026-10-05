<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Legacy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\SubqueryRule;

#[CoversClass(SubqueryRule::class)]
#[Medium]
final class SubqueryRuleTest extends TestCase
{
    public function testSubselectLowersTheQueryOfAScalarSubquery(): void
    {
        self::assertSame('SELECT (SELECT 1 UNION SELECT 2) AS v FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select (select 1 union select 2) as v from t')->toString());
        self::assertSame('SELECT (SELECT 1 UNION SELECT 2) AS v FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select (select 1 union select 2) as v from t')->toString());
    }

    public function testBodyLiftsTheOrderingOfTheLastOperand(): void
    {
        self::assertSame('SELECT 1 IN (SELECT a FROM t UNION SELECT b FROM u ORDER BY 1 LIMIT 1) AS w FROM v', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select 1 in (select a from t union select b from u order by 1 limit 1) as w from v')->toString());
    }

    public function testOperandLowersParenthesizedOperandsWithTheirOrdering(): void
    {
        self::assertSame('SELECT (SELECT 1 UNION (SELECT 2) LIMIT 1) AS v FROM t', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select (select 1 union (select 2) limit 1) as v from t')->toString());
    }

    public function testParenLowersNestedParentheses(): void
    {
        self::assertSame('SELECT ((SELECT 1)) AS v FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select ((select 1)) as v from t')->toString());
    }

    public function testOperandKeepsAnOrderingAfterALockedBlock(): void
    {
        self::assertSame('SELECT (SELECT a FROM t FOR UPDATE ORDER BY 1) AS v FROM u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select (select a from t for update order by 1) as v from u')->toString());
    }

    public function testOperandOrdersTheRowsOfAnOrderedBlock(): void
    {
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT (SELECT a FROM t LIMIT 1 ORDER BY b) FROM u');

        self::assertSame('SELECT (SELECT a FROM t LIMIT 1 ORDER BY b) FROM u', $operation->toString());
    }
}
