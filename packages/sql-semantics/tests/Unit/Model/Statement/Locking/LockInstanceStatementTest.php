<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Locking\LockInstanceStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockInstanceStatement::class)]
#[Medium]
final class LockInstanceStatementTest extends TestCase
{
    public function testWithOriginRetainsTheLockRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('lock instance for backup');
        self::assertInstanceOf(LockInstanceStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('LOCK INSTANCE FOR BACKUP', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testRejectsAReleaseWithoutBackupLocks(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('LOCK INSTANCE FOR BACKUP');
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin(new Origin('s0', $statement->source, Dialect::MySql, [], $legacy->origin->context));
    }

    public function testRejectsAnotherDatabaseDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('LOCK INSTANCE FOR BACKUP');
        $this->expectException(InvalidStructure::class);
        new LockInstanceStatement(new Origin('s0', $statement->source, Dialect::Sqlite));
    }
}
