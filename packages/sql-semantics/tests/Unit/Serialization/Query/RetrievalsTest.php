<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoTableStatement;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoVariablesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\Retrievals;

#[CoversClass(Retrievals::class)]
#[Medium]
final class RetrievalsTest extends TestCase
{
    public function testWriteReturnsNullForAPlainQuery(): void
    {
        self::assertNull(Retrievals::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')));
    }

    public function testDestinationWritesUserVariables(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1, 2 INTO @a, @b');
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement);
        self::assertSame('INTO @`a`, @`b`', Retrievals::destination($statement)->toString());
    }

    public function testTableWritesThePersistence(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 INTO TEMP n');
        self::assertInstanceOf(SelectIntoTableStatement::class, $statement);
        self::assertSame('INTO TEMPORARY TABLE "n"', Retrievals::table($statement)->toString());
    }

    public function testLeftmostPlacesIntoAfterTheFirstSelectList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('WITH c AS (SELECT 1) SELECT 2 UNION SELECT 3');
        $tree = Retrievals::leftmost(\SqlSemantics\Serialization\Statements::write($statement), new Tree('into', [Build::keyword('INTO n')]));
        self::assertSame('WITH "c" AS (SELECT 1) SELECT 2 INTO n UNION SELECT 3', $tree->toString());
    }

    public function testLastPlacesIntoBeforeTheLockingClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a FROM t FOR UPDATE');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $statement);
        $tree = Retrievals::last($statement, new Tree('into', [Build::keyword('INTO @a')]));
        self::assertSame('SELECT `a` AS `a` FROM `t` INTO @a FOR UPDATE', $tree->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.6.51'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-5.7.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    public function testLastNamesDualBeforeALimitFollowedByInto(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        $statement = $binder->bind("SELECT SQL_NO_CACHE 1 FROM DUAL LIMIT 9485349219540000000 INTO DUMPFILE 'x'");
        self::assertSame("SELECT SQL_NO_CACHE 1 FROM DUAL LIMIT 9485349219540000000 INTO DUMPFILE 'x'", $statement->toString());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteSpellsEveryIntoForm')]
    public function testWriteSpellsEveryIntoForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, Retrievals::write($statement)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteSpellsEveryIntoForm(): iterable
    {
        return [
            'SELECT a, b INTO @x, @y FROM t (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a, b INTO @x, @y FROM t', 'SELECT `a` AS `a`, `b` AS `b` FROM `t` INTO @`x`, @`y`'],
            'SELECT a FROM t INTO @x (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t INTO @x', 'SELECT `a` AS `a` FROM `t` INTO @`x`'],
            'SELECT a FROM t INTO @x FOR UPDATE (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t INTO @x FOR UPDATE', 'SELECT `a` AS `a` FROM `t` INTO @`x` FOR UPDATE'],
            'SELECT a FROM t FOR UPDATE INTO @x (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t FOR UPDATE INTO @x', 'SELECT `a` AS `a` FROM `t` INTO @`x` FOR UPDATE'],
            'SELECT a FROM t WHERE a > 1 ORDER BY a LIMIT 1 INTO @x (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t WHERE a > 1 ORDER BY a LIMIT 1 INTO @x', 'SELECT `a` AS `a` FROM `t` WHERE (`a` > 1) ORDER BY `a` ASC LIMIT 1 INTO @`x`'],
            'SELECT a FROM t INTO OUTFILE \'/tmp/a\' (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t INTO OUTFILE \'/tmp/a\'', 'SELECT `a` AS `a` FROM `t` INTO OUTFILE \'/tmp/a\''],
            'SELECT a FROM t INTO OUTFILE \'/tmp/a\' CHARACTER SET utf8mb4 FIELDS TERMINATED BY \',\' OPTIONALLY ENCL... 6' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t INTO OUTFILE \'/tmp/a\' CHARACTER SET utf8mb4 FIELDS TERMINATED BY \',\' OPTIONALLY ENCLOSED BY \'"\' LINES STARTING BY \'>\' TERMINATED BY \'\\n\'', 'SELECT `a` AS `a` FROM `t` INTO OUTFILE \'/tmp/a\' CHARACTER SET `utf8mb4` FIELDS TERMINATED BY \',\' OPTIONALLY ENCLOSED BY \'"\' LINES STARTING BY \'>\' TERMINATED BY \'\\n\''],
            'SELECT a FROM t INTO OUTFILE \'/tmp/a\' LINES TERMINATED BY \';\' (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t INTO OUTFILE \'/tmp/a\' LINES TERMINATED BY \';\'', 'SELECT `a` AS `a` FROM `t` INTO OUTFILE \'/tmp/a\' LINES TERMINATED BY \';\''],
            'SELECT a FROM t INTO DUMPFILE \'/tmp/a\' (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t INTO DUMPFILE \'/tmp/a\'', 'SELECT `a` AS `a` FROM `t` INTO DUMPFILE \'/tmp/a\''],
            'SELECT a FROM t UNION SELECT b FROM t INTO @x (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a FROM t UNION SELECT b FROM t INTO @x', 'SELECT `a` AS `a` FROM `t` UNION SELECT `b` AS `b` FROM `t` INTO @`x`'],
            '(SELECT a FROM t) INTO @x (MySql)' => [Dialect::MySql, null, ['CREATE TABLE t (a INT, b INT)'], '(SELECT a FROM t) INTO @x', 'SELECT `a` AS `a` FROM `t` INTO @`x`'],
            'SELECT a INTO x FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a INTO x FROM t', 'SELECT "a" AS "a" INTO TABLE "x" FROM "public"."t"'],
            'SELECT a, b INTO TEMP x FROM t WHERE a > 1 (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a, b INTO TEMP x FROM t WHERE a > 1', 'SELECT "a" AS "a", "b" AS "b" INTO TEMPORARY TABLE "x" FROM "public"."t" WHERE ("a" > 1)'],
            'SELECT a INTO UNLOGGED x FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a INTO UNLOGGED x FROM t', 'SELECT "a" AS "a" INTO UNLOGGED TABLE "x" FROM "public"."t"'],
            'SELECT a INTO TABLE s.x FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a INTO TABLE s.x FROM t', 'SELECT "a" AS "a" INTO TABLE "s"."x" FROM "public"."t"'],
            'SELECT DISTINCT a INTO x FROM t ORDER BY a (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT DISTINCT a INTO x FROM t ORDER BY a', 'SELECT DISTINCT "a" AS "a" INTO TABLE "x" FROM "public"."t" ORDER BY "a" ASC'],
            'SELECT a INTO x FROM t UNION SELECT b FROM t (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'SELECT a INTO x FROM t UNION SELECT b FROM t', 'SELECT "a" AS "a" INTO TABLE "x" FROM "public"."t" UNION SELECT "b" AS "b" FROM "public"."t"'],
            'WITH q AS (SELECT 1 AS c) SELECT c INTO x FROM q (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], 'WITH q AS (SELECT 1 AS c) SELECT c INTO x FROM q', 'WITH "q" AS (SELECT 1 AS "c") SELECT "c" AS "c" INTO TABLE "x" FROM "q"'],
            '(SELECT a INTO x FROM t) UNION (SELECT b FROM t) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a INT, b INT)'], '(SELECT a INTO x FROM t) UNION (SELECT b FROM t)', 'SELECT "a" AS "a" INTO TABLE "x" FROM "public"."t" UNION SELECT "b" AS "b" FROM "public"."t"'],
        ];
    }
}
