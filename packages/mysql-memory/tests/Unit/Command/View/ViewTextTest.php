<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\ViewText;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

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

    public function testMembersWritesATableListAsJoins(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT t.a FROM t, t AS u, t AS w');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select `t`.`a` AS `a` from ((`t` join `t` `u`) join `t` `w`)', $result->rows[0][1] ?? '');
    }

    public function testJoinWritesARightJoinAsALeftJoinOfTheOperandsSwapped(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT t.a FROM t RIGHT JOIN t AS u ON t.a = u.a');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select `t`.`a` AS `a` from (`t` `u` left join `t` on((`t`.`a` = `u`.`a`)))', $result->rows[0][1] ?? '');
    }

    public function testJoinLeavesAJoinWithUsingAsItWasWritten(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');

        $session->query('CREATE VIEW v AS SELECT t.a FROM t JOIN t AS u USING (a)');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS SELECT t.a FROM t JOIN t AS u USING (a)', $result->rows[0][1] ?? '');
    }

    public function testQueryWritesItsExpressionsThroughAWriterOfItsOwn(): void
    {
        $session = (new Instance())->connect();
        $text = new ViewText($session->analyze('SELECT 1')->facts, '', '');

        self::assertSame($text, $text->expressions->text);
    }

    public function testSelectWritesTheWindowsTheCallsUseAfterHaving(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT, p INT, o INT)');
        $session->query('CREATE VIEW v AS SELECT id, SUM(id) OVER (v) a FROM u WINDOW w AS (PARTITION BY p), v AS (w ORDER BY o), x AS (ORDER BY id)');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select `u`.`id` AS `id`,sum(`u`.`id`) OVER (`v` )  AS `a` from `u` window `w` AS (PARTITION BY `u`.`p` ) , `v` AS (`w` ORDER BY `u`.`o` ) ', $result->rows[0][1] ?? '');
    }

    public function testQueryWritesTheHintsAfterTheSelectOfTheFirstBlock(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, KEY ka (a))');
        $session->query('CREATE VIEW c AS WITH w AS (SELECT /*+ INDEX(t ka) */ a FROM t) SELECT /*+ NO_INDEX(t) */ w.a FROM w, t UNION SELECT a FROM t');

        $result = $session->query('SHOW CREATE VIEW c')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS with `w` as (select `t`.`a` AS `a` from `t`) select /*+ INDEX(`t`@`select#2` `ka`) NO_INDEX(`t`@`select#1`) */ `w`.`a` AS `a` from (`w` join `t`) union select `t`.`a` AS `a` from `t`', $result->rows[0][1] ?? '');
    }
}
