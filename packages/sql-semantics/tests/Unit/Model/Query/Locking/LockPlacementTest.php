<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Locking\AllRowLock;
use SqlSemantics\Model\Query\Locking\LockPlacement;
use SqlSemantics\Model\Query\Locking\LockStrength;
use SqlSemantics\Model\Query\Locking\NamedRowLock;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockPlacement::class)]
#[Medium]
final class LockPlacementTest extends TestCase
{
    public function testValidateAcceptsLocksOnTheQueryInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t FOR NO KEY UPDATE OF t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        LockPlacement::validate($statement->locks, $statement->origin, $statement->relations);
        self::assertSame(LockStrength::NoKeyUpdate, $statement->locks[0]->strength);
    }

    public function testValidateRejectsLocksInSqlite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        LockPlacement::validate([new AllRowLock(LockStrength::Update)], $statement->origin, []);
    }

    public function testValidateRejectsAPostgreSqlStrengthInMySql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        LockPlacement::validate([new AllRowLock(LockStrength::KeyShare)], $statement->origin, []);
    }

    public function testValidateRejectsANamedTargetOutsideTheQueryInput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t FOR UPDATE OF t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(NamedRowLock::class, $statement->locks[0]);
        $this->expectException(InvalidStructure::class);
        LockPlacement::validate($statement->locks, new Origin('s1', $statement->source, Dialect::PostgreSql), []);
    }

    public function testValidateRejectsAMySqlTableLockedByTwoClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t FOR UPDATE OF t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        LockPlacement::validate([...$statement->locks, new AllRowLock(LockStrength::Share)], $statement->origin, $statement->relations);
    }

    public function testRepeatedFindsATargetNamedTwice(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)')))->bind('SELECT t.id FROM t, u FOR UPDATE OF t FOR SHARE OF u, t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertTrue(LockPlacement::repeated($statement->locks, $statement->relations));
        self::assertFalse(LockPlacement::repeated([$statement->locks[0]], $statement->relations));
    }

    public function testRepeatedFindsAClauseWithoutOfNextToAnotherClauseOverAStoredTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT id FROM t FOR UPDATE OF t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertTrue(LockPlacement::repeated([new AllRowLock(LockStrength::Update), ...$statement->locks], $statement->relations));
        self::assertTrue(LockPlacement::repeated([new AllRowLock(LockStrength::Update), new AllRowLock(LockStrength::Share)], $statement->relations));
        self::assertFalse(LockPlacement::repeated([new AllRowLock(LockStrength::Update)], $statement->relations));
        self::assertFalse(LockPlacement::repeated([new AllRowLock(LockStrength::Update), new AllRowLock(LockStrength::Share)], []));
    }
}
