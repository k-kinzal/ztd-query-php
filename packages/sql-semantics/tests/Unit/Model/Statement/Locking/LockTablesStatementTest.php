<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Locking\MySqlLockMode;
use SqlSemantics\Model\Statement\Locking\LockTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockTablesStatement::class)]
#[Medium]
final class LockTablesStatementTest extends TestCase
{
    public function testWithOriginPreservesIndependentAccessModes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('LOCK TABLES t AS a READ LOCAL, t AS b WRITE');
        self::assertInstanceOf(LockTablesStatement::class, $statement);
        self::assertSame(MySqlLockMode::ReadLocal, $statement->locks[0]->mode);
        self::assertSame(MySqlLockMode::Write, $statement->locks[1]->mode);
        self::assertSame(['a', 'b'], [$statement->locks[0]->table->alias, $statement->locks[1]->table->alias]);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->locks, $copy->locks);
        self::assertSame('LOCK TABLES `t` AS `a` READ LOCAL, `t` AS `b` WRITE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($copy), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($copy))));
    }

    public function testWithLocksReplacesRequestsImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('LOCK TABLES t READ');
        $other = $binder->bind('LOCK TABLES t WRITE');
        self::assertInstanceOf(LockTablesStatement::class, $statement);
        self::assertInstanceOf(LockTablesStatement::class, $other);
        $changed = $statement->withLocks($other->locks);
        self::assertSame(MySqlLockMode::Write, $changed->locks[0]->mode);
        self::assertSame(MySqlLockMode::Read, $statement->locks[0]->mode);
    }

    public function testRejectsEmptyRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK TABLES t READ');
        $this->expectException(InvalidStructure::class);
        new LockTablesStatement($statement->origin, []);
    }
}
