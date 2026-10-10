<?php

declare(strict_types=1);

namespace Tests\Unit\Command\View;

use MySqlMemory\Command\View\WindowText;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(WindowText::class)]
#[Small]
final class WindowTextTest extends TestCase
{
    public function testCallWritesTheFunctionInLowerCaseWithoutNullTreatment(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('CREATE VIEW v AS SELECT NTH_VALUE(id, 1) FROM FIRST RESPECT NULLS OVER (ORDER BY id) a, LEAD(id) RESPECT NULLS OVER () b FROM u');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select nth_value(`u`.`id`,1) OVER (ORDER BY `u`.`id` )  AS `a`,lead(`u`.`id`) OVER ()  AS `b` from `u`', $result->rows[0][1] ?? '');
    }

    public function testAggregateWritesCountOfAllRowsAsCountOfZero(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('CREATE VIEW v AS SELECT COUNT(*) OVER w a FROM u WINDOW w AS (ORDER BY id DESC)');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select count(0) OVER `w` AS `a` from `u` window `w` AS (ORDER BY `u`.`id` desc ) ', $result->rows[0][1] ?? '');
    }

    public function testOverWritesTheQuotedNameOfANamedWindow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('CREATE VIEW v AS SELECT RANK() OVER w a FROM u WINDOW w AS ()');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select rank() OVER `w` AS `a` from `u` window `w` AS () ', $result->rows[0][1] ?? '');
    }

    public function testSpecificationWritesPartitionOrderAndFrame(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT, p INT, o INT)');
        $session->query('CREATE VIEW v AS SELECT RANK() OVER (PARTITION BY p, o ORDER BY id DESC, o) a FROM u');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select rank() OVER (PARTITION BY `u`.`p`,`u`.`o` ORDER BY `u`.`id` desc,`u`.`o` )  AS `a` from `u`', $result->rows[0][1] ?? '');
    }

    public function testFrameWritesTheBetweenForm(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT, o INT)');
        $session->query('CREATE VIEW v AS SELECT NTILE(3) OVER (ORDER BY o ROWS 2 PRECEDING) a FROM u');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select ntile(3) OVER (ORDER BY `u`.`o` ROWS BETWEEN 2 PRECEDING AND CURRENT ROW)  AS `a` from `u`', $result->rows[0][1] ?? '');
    }

    public function testBoundWritesAnIntervalWithItsUnitInLowerCase(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (d DATE)');
        $session->query("CREATE VIEW v AS SELECT COUNT(*) OVER (ORDER BY d RANGE BETWEEN INTERVAL 1 DAY PRECEDING AND INTERVAL '1:2' DAY_HOUR FOLLOWING) a FROM t");

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith("AS select count(0) OVER (ORDER BY `t`.`d` RANGE BETWEEN INTERVAL 1 day  PRECEDING AND INTERVAL '1:2' day_hour  FOLLOWING)  AS `a` from `t`", $result->rows[0][1] ?? '');
    }

    public function testClauseLeavesOutTheWindowsNoCallUses(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('CREATE VIEW v AS SELECT id FROM u WINDOW w AS (ORDER BY id)');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('AS select `u`.`id` AS `id` from `u`', $result->rows[0][1] ?? '');
    }

    public function testUsedAnswersTheWindowsNamedAfterOverAndRefined(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE u (id INT)');
        $session->query('CREATE VIEW v AS SELECT RANK() OVER w a, RANK() OVER (x ORDER BY id) b FROM u WINDOW w AS (), x AS (), y AS ()');

        $result = $session->query('SHOW CREATE VIEW v')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertStringEndsWith('window `w` AS () , `x` AS () ', $result->rows[0][1] ?? '');
    }
}
