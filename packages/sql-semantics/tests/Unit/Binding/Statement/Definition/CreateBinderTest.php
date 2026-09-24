<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\CreateBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateBinder::class)]
#[Medium]
final class CreateBinderTest extends TestCase
{
    public function testBindDiagnosesAColumnlessMySqlTableInsteadOfInventingEmptyParentheses(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-9.1.0'))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('at least one column');
        $binder->bind('CREATE TABLE t', strict: false);
    }

    public function testBindAllowsPostgreSqlToDeclareAZeroColumnTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t ()');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertSame([], $statement->definition->table->columns);
    }

    public function testBindReadsPostgreSqlTemplatesAndExclusions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE s(a INTEGER)')))->bind('CREATE TABLE t (LIKE s, x INTEGER, EXCLUDE (x WITH =))');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\CreateTableStatement::class, $statement);
        self::assertSame(0, $statement->templates[0]->position);
        self::assertCount(1, $statement->exclusions);
    }

    public function testBindAcceptsARepeatedTemporaryKeywordOnMySql5(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INT)')))->bind('CREATE TEMPORARY TEMPORARY TABLE u LIKE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Table\CreateTableLikeStatement::class, $statement);
        self::assertTrue($statement->temporary);
    }
}
