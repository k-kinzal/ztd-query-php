<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\InsertBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InsertBinder::class)]
#[Medium]
final class InsertBinderTest extends TestCase
{
    public function testStatementChoosesTheInputFormFromTheBoundOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertDefaultValuesStatement::class, $binder->bind('INSERT INTO t DEFAULT VALUES'));
        $select = $binder->bind('INSERT INTO t SELECT a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $select);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $select->query);
        $values = $binder->bind('INSERT INTO t VALUES (1), (2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $values);
        self::assertCount(2, $values->rows);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Policy\PostgreSqlInsertion::class, $values->policy);
        $set = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('INSERT INTO t SET a = 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSetStatement::class, $set);
        self::assertCount(1, $set->writes);
        self::assertSame('INSERT INTO `t` SET `a` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($set));
    }

    public function testStatementKeepsAnOrderedValuesInputAsAQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('INSERT INTO t VALUES (1) ORDER BY 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ValuesStatement::class, $statement->query);
        self::assertCount(1, $statement->query->orderBy);
        self::assertSame('INSERT INTO "public"."t" VALUES (1) ORDER BY 1 ASC', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testStatementKeepsALimitedValuesInputAsAQuery(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('INSERT INTO t VALUES (1) LIMIT 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertSelectStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\ValuesStatement::class, $statement->query);
        self::assertSame('1', $statement->query->limit?->spelling());
        self::assertSame('INSERT INTO "public"."t" VALUES (1) LIMIT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
