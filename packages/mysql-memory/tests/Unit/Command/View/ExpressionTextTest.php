<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ExpressionText;
use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;

#[CoversClass(ExpressionText::class)]
#[Small]
final class ExpressionTextTest extends TestCase
{
    public function testScalarsWritesEachExpression(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['1', 'NULL'], (new ExpressionText(new ViewText($session->analyze('SELECT 1')->facts, '', '')))->scalars([new NumberLiteral('1'), new NullLiteral()]));
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

        self::assertSame('(1 + 2)', (new ExpressionText(new ViewText($session->analyze('SELECT 1')->facts, '', '')))->binary(new NumberLiteral('1'), '+', new NumberLiteral('2')));
    }

    public function testWrapPutsTheOperandBetweenTheTexts(): void
    {
        $session = (new Instance())->connect();

        self::assertSame(['-(1)', null], [(new ExpressionText(new ViewText($session->analyze('SELECT 1')->facts, '', '')))->wrap(new NumberLiteral('1'), '-(', ')'), (new ExpressionText(new ViewText($session->analyze('SELECT 1')->facts, '', '')))->wrap(new NumberLiteral('1'), '', '', true)]);
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

    public function testLiteralWritesEachKindOfLiteral(): void
    {
        $session = (new Instance())->connect();
        $text = new ExpressionText(new ViewText($session->analyze('SELECT 1')->facts, '', ''));

        self::assertSame(['1', '-(2)', '3', "'it\\'s'", 'NULL', 'false', null], [$text->literal(new NumberLiteral('1')), $text->literal(new SignedLiteral(true, new NumberLiteral('2'))), $text->literal(new SignedLiteral(false, new NumberLiteral('3'))), $text->literal(new StringLiteral(["it's"])), $text->literal(new NullLiteral()), $text->literal(new BooleanLiteral(false)), $text->literal(new Grouped(new NumberLiteral('4')))]);
    }

    public function testOperatorWritesACollationAndANullTest(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b VARCHAR(10))');

        $session->query('CREATE VIEW v AS SELECT b COLLATE utf8mb4_bin, a IS NOT NULL FROM t');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select (`t`.`b` collate utf8mb4_bin) AS `b COLLATE utf8mb4_bin`,(`t`.`a` is not null) AS `a IS NOT NULL` from `t`', $result->rows[0][1] ?? '');
    }

    public function testOperatorAnswersNullForAnExpressionThatIsNotAnOperator(): void
    {
        $session = (new Instance())->connect();

        self::assertNull((new ExpressionText(new ViewText($session->analyze('SELECT 1')->facts, '', '')))->operator(new NumberLiteral('1')));
    }

    public function testUnaryWritesPlusAsItsOperandAndNotAsAComparisonWithZero(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT +a, -a, ~a, !a FROM t');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select `t`.`a` AS `a`,-(`t`.`a`) AS `-a`,~(`t`.`a`) AS `~a`,(0 = `t`.`a`) AS `!a` from `t`', $result->rows[0][1] ?? '');
    }

    public function testLikeWritesNotLikeAsANegation(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (b VARCHAR(10))');

        $session->query("CREATE VIEW v AS SELECT b NOT LIKE 'x', b LIKE 'y' FROM t");

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith("AS select (not((`t`.`b` like 'x'))) AS `b NOT LIKE 'x'`,(`t`.`b` like 'y') AS `b LIKE 'y'` from `t`", $result->rows[0][1] ?? '');
    }

    public function testLikeLeavesAQueryWithEscapeAsItWasWritten(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (b VARCHAR(10))');

        $session->query("CREATE VIEW v AS SELECT b LIKE 'x' ESCAPE '!' FROM t");

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith("AS SELECT b LIKE 'x' ESCAPE '!' FROM t", $result->rows[0][1] ?? '');
    }

    public function testInQueryWritesNotInWithTheSubquery(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT a FROM t WHERE a NOT IN (SELECT a FROM t)');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select `t`.`a` AS `a` from `t` where `t`.`a` not in (select `t`.`a` from `t`)', $result->rows[0][1] ?? '');
    }
}
