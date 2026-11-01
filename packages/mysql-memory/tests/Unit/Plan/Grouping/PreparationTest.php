<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Grouping;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Grouping\Preparation;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Preparation::class)]
#[Small]
final class PreparationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<list<string>>, int, int}>
     */
    public static function providerLegacyPhases(): iterable
    {
        yield 'MySQL 5.6' => ['5.6.51', [['1', '1', '2'], ['3', '1', '3'], ['4', '1', '4']], 4, 0];
        yield 'MySQL 5.7' => ['5.7.44', [['3', '1', '3'], ['4', '1', '4'], ['5', '1', '5']], 5, 2];
    }

    /**
     * @param list<list<string>> $rows
     */
    #[DataProvider('providerLegacyPhases')]
    public function testPlanPreparesLegacySubqueriesAtTheirReleaseBoundary(string $version, array $rows, int $final, int $pruned): void
    {
        $session = (new Instance($version))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT @x:=@x+1,(SELECT COUNT(DISTINCT @x:=2) FROM t i WHERE i.n=o.n),@x FROM t o')[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($rows, $result->rows);
        self::assertSame($final, $session->variables->user('x')[0]);

        $session->query('SET @x=0;SELECT (SELECT COUNT(DISTINCT @x:=2) FROM t i WHERE i.n=o.n) FROM t o WHERE FALSE');

        self::assertSame($pruned, $session->variables->user('x')[0]);
    }

    /**
     * @return iterable<string, array{string, int|null}>
     */
    public static function providerBoundaries(): iterable
    {
        yield 'empty scan' => ['SELECT COUNT(DISTINCT @x:=2) FROM t', 2];
        yield 'row filter' => ['SELECT COUNT(DISTINCT @x:=2) FROM t WHERE n<0', 2];
        yield 'false filter' => ['SELECT COUNT(DISTINCT @x:=2) FROM t WHERE FALSE', 0];
        yield 'zero limit' => ['SELECT COUNT(DISTINCT @x:=2) FROM t LIMIT 0', 0];
        yield 'positive offset' => ['SELECT COUNT(DISTINCT @x:=2) FROM t LIMIT 0 OFFSET 1', 2];
        yield 'null input' => ['SELECT COUNT(DISTINCT @x:=NULL,@y:=10) FROM t', null];
        yield 'false having assignment' => ['SELECT COUNT(DISTINCT @x:=2) FROM t HAVING (@y:=0)=1', 0];
        yield 'nested empty scan' => ['SELECT (SELECT COUNT(DISTINCT @x:=2) FROM t) FROM t', 2];
        yield 'nested false outer' => ['SELECT (SELECT COUNT(DISTINCT @x:=2) FROM t) FROM t WHERE FALSE', 0];
    }

    #[DataProvider('providerBoundaries')]
    public function testPlanPreparesEmptyInputsAndSuppressesPrunedBlocks(string $sql, ?int $expected): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);SET @x=0,@y=0');
        $session->query($sql);

        self::assertSame($expected, $session->variables->user('x')[0]);
        self::assertSame(0, $session->variables->user('y')[0]);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerKeys(): iterable
    {
        yield 'existing' => ['n=1', 2];
        yield 'missing' => ['n=9', 0];
        yield 'null' => ['n=NULL', 0];
        yield 'session value' => ['n=@y', 0];
    }

    #[DataProvider('providerKeys')]
    public function testMissingSuppressesChecksForAbsentConstantPrimaryKeys(string $condition, int $expected): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT PRIMARY KEY);INSERT INTO t VALUES(1),(2);SET @x=0,@y=9');
        $session->query('SELECT COUNT(DISTINCT @x:=2) FROM t WHERE ' . $condition);

        self::assertSame($expected, $session->variables->user('x')[0]);
    }

    public function testEmptyTotalSuppressesStaticallyRejectedAndAbsentKeyRows(): void
    {
        $session = (new Instance('9.1.0'))->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT PRIMARY KEY)');
        $false = $session->query('SELECT n,COUNT(*) FROM t WHERE FALSE GROUP BY n WITH ROLLUP')[0];
        $missing = $session->query('SELECT n,COUNT(*) FROM t WHERE n=1 GROUP BY n WITH ROLLUP')[0];

        self::assertInstanceOf(ResultSet::class, $false);
        self::assertSame([], $false->rows);
        self::assertInstanceOf(ResultSet::class, $missing);
        self::assertSame([], $missing->rows);
    }

    public function testPlanPreparesCorrelatedInputsOnceBeforeTheOuterProjection(): void
    {
        $session = (new Instance())->connect();
        $session->query('USE mysql;CREATE TABLE t(n INT);INSERT INTO t VALUES(2),(1),(2);SET @x=0');
        $result = $session->query('SELECT @x:=@x+1,(SELECT COUNT(DISTINCT @x:=2) FROM t i WHERE i.n=o.n),@x FROM t o')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['3', '1', '3'], ['4', '1', '4'], ['5', '1', '5']], $result->rows);
        self::assertSame(5, $session->variables->user('x')[0]);
    }
}
