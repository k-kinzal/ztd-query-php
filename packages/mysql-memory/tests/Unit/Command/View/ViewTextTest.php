<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(ViewText::class)]
#[Small]
final class ViewTextTest extends TestCase
{
    public function testQueryWritesASetOperationAndACommonTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('CREATE VIEW u AS SELECT a FROM t UNION SELECT a FROM t');
        $session->query('CREATE VIEW c AS WITH w AS (SELECT a FROM t) SELECT * FROM w');

        $result1 = $session->query('SHOW CREATE VIEW u')[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertStringEndsWith('AS select `t`.`a` AS `a` from `t` union select `t`.`a` AS `a` from `t`', $result1->rows[0][1] ?? '');
        $result2 = $session->query('SHOW CREATE VIEW c')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertStringEndsWith('AS with `w` as (select `t`.`a` AS `a` from `t`) select `w`.`a` AS `a` from `w`', $result2->rows[0][1] ?? '');
    }

    public function testSelectWritesEachClauseInLowerCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');

        $session->query('CREATE VIEW v AS SELECT COUNT(*), SUM(a), MAX(b), AVG(DISTINCT a) FROM t GROUP BY b HAVING COUNT(*) > 1 ORDER BY b DESC LIMIT 5');

        $result3 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertStringEndsWith('AS select count(0) AS `COUNT(*)`,sum(`t`.`a`) AS `SUM(a)`,max(`t`.`b`) AS `MAX(b)`,avg(distinct `t`.`a`) AS `AVG(DISTINCT a)` from `t` group by `t`.`b` having (count(0) > 1) order by `t`.`b` desc limit 5', $result3->rows[0][1] ?? '');
    }

    public function testTailWritesNothingWithoutOrderOrLimit(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1');

        self::assertSame('', (new ViewText($operation->facts, '', ''))->tail([], null));
    }

    public function testRelationWritesADerivedTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT a FROM (SELECT a FROM t) AS q');

        $result4 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result4);
        self::assertStringEndsWith('AS select `q`.`a` AS `a` from (select `t`.`a` AS `a` from `t`) `q`', $result4->rows[0][1] ?? '');
    }

    public function testTableQualifiesAnotherDatabase(): void
    {
        $session = (new Instance())->connect();
        $text = new ViewText($session->analyze('SELECT 1')->facts, 'd', 'd');

        self::assertSame(['`t`', '`e`.`t`'], [$text->table(null, 't'), $text->table('e', 't')]);
    }

    public function testColumnAnswersNullWithoutAResolvedColumn(): void
    {
        $session = (new Instance())->connect();

        self::assertNull((new ViewText($session->analyze('SELECT 1')->facts, '', ''))->column(null));
    }

    public function testScalarsWritesEachExpression(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['1', 'NULL'], (new ViewText($session->analyze('SELECT 1')->facts, '', ''))->scalars([new NumberLiteral('1'), new NullLiteral()]));
    }

    public function testScalarWritesOperatorsInParenthesesAndNegativeNumbers(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');

        $session->query("CREATE VIEW v AS SELECT a * 2 - 3, -2, NOT a, a < 1 AND b > 'x' OR a IS NULL, TRUE FROM t");

        $result5 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result5);
        self::assertStringEndsWith("AS select ((`t`.`a` * 2) - 3) AS `a * 2 - 3`,-(2) AS `-2`,(0 = `t`.`a`) AS `NOT a`,(((`t`.`a` < 1) and (`t`.`b` > 'x')) or (`t`.`a` is null)) AS `a < 1 AND b > 'x' OR a IS NULL`,true AS `TRUE` from `t`", $result5->rows[0][1] ?? '');
    }

    public function testBinaryWritesBothOperands(): void
    {
        $session = (new Instance())->connect();

        self::assertSame('(1 + 2)', (new ViewText($session->analyze('SELECT 1')->facts, '', ''))->binary(new NumberLiteral('1'), '+', new NumberLiteral('2')));
    }

    public function testWrapPutsTheOperandBetweenTheTexts(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['-(1)', null], [(new ViewText($session->analyze('SELECT 1')->facts, '', ''))->wrap(new NumberLiteral('1'), '-(', ')'), (new ViewText($session->analyze('SELECT 1')->facts, '', ''))->wrap(new NumberLiteral('1'), '', '', true)]);
    }

    public function testNotNegatesAComparison(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT NOT (a = 1) AS x FROM t');

        $result6 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result6);
        self::assertStringEndsWith('AS select (`t`.`a` <> 1) AS `x` from `t`', $result6->rows[0][1] ?? '');
    }

    public function testBetweenAndInWriteTheirOperands(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT a IN (1,2), a BETWEEN 1 AND 2 FROM t');

        $result7 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result7);
        self::assertStringEndsWith('AS select (`t`.`a` in (1,2)) AS `a IN (1,2)`,(`t`.`a` between 1 and 2) AS `a BETWEEN 1 AND 2` from `t`', $result7->rows[0][1] ?? '');
    }

    public function testInWritesTheList(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT 1 IN (1, 2)');
        $select = $operation->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertStringEndsWith('AS `1 IN (1, 2)`', (string) (new ViewText($operation->facts, '', ''))->select($select));
    }

    public function testCallAndAggregateWriteLowerCaseNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');

        $session->query("CREATE VIEW v AS SELECT CONCAT(b, 'x'), IFNULL(a, 0), COUNT(a) FROM t GROUP BY a, b");

        $result8 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result8);
        self::assertStringEndsWith("AS select concat(`t`.`b`,'x') AS `CONCAT(b, 'x')`,ifnull(`t`.`a`,0) AS `IFNULL(a, 0)`,count(`t`.`a`) AS `COUNT(a)` from `t` group by `t`.`a`,`t`.`b`", $result8->rows[0][1] ?? '');
    }

    public function testAggregateWritesCountOfTheStarAsCountOfZero(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT COUNT(*), COUNT(DISTINCT 1, 2)');
        $select = $operation->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame('select count(0) AS `COUNT(*)`,count(distinct 1,2) AS `COUNT(DISTINCT 1, 2)`', (new ViewText($operation->facts, '', ''))->select($select));
    }

    public function testBranchesWriteTheCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query("CREATE VIEW v AS SELECT CASE WHEN a > 1 THEN 'big' ELSE 'small' END AS s FROM t");

        $result9 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result9);
        self::assertStringEndsWith("AS select (case when (`t`.`a` > 1) then 'big' else 'small' end) AS `s` from `t`", $result9->rows[0][1] ?? '');
    }

    public function testSubqueryWritesTheQueryBetweenTheTexts(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT (SELECT MAX(a) FROM t) AS m, EXISTS (SELECT 1 FROM t) FROM t WHERE a IN (SELECT a FROM t)');

        $result10 = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result10);
        self::assertStringEndsWith('AS select (select max(`t`.`a`) from `t`) AS `m`,exists(select 1 from `t`) AS `EXISTS (SELECT 1 FROM t)` from `t` where `t`.`a` in (select `t`.`a` from `t`)', $result10->rows[0][1] ?? '');
    }
}
