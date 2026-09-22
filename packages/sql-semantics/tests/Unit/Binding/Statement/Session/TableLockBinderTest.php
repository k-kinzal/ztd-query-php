<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Locking\LockRelationsStatement;
use SqlSemantics\Model\Statement\Locking\LockTablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Session\TableLockBinder::class)]
#[Medium]
final class TableLockBinderTest extends TestCase
{
    public function testBindResolvesTargetsAgainstTheSuppliedSnapshot(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE app.t(id INTEGER)');
        $statement = (new Binder($schema))->bind('LOCK app.t');
        self::assertInstanceOf(LockRelationsStatement::class, $statement);
        self::assertSame($schema->tables[0], $statement->tables[0]->declaration);
        self::assertSame(\SqlSemantics\Model\Locking\PostgreSqlLockMode::AccessExclusive, $statement->mode);
    }

    public function testMysqlRetainsIndependentAliasesForOneTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK TABLES t AS a READ, t AS b WRITE');
        self::assertInstanceOf(LockTablesStatement::class, $statement);
        self::assertSame($statement->locks[0]->table->declaration, $statement->locks[1]->table->declaration);
        self::assertNotSame($statement->locks[0]->table->id, $statement->locks[1]->table->id);
        self::assertSame('a', $statement->locks[0]->table->alias);
        self::assertSame('b', $statement->locks[1]->table->alias);
    }
}
