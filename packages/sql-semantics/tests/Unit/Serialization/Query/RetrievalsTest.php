<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
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
        self::assertSame("SELECT 1 FROM DUAL LIMIT 9485349219540000000 INTO DUMPFILE 'x'", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
