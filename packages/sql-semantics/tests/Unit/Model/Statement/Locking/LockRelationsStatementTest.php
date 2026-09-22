<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Locking;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Locking\PostgreSqlLockMode;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Statement\Locking\LockRelationsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LockRelationsStatement::class)]
#[Medium]
final class LockRelationsStatementTest extends TestCase
{
    public function testWithOriginPreservesModeAndAcquisitionOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('LOCK ONLY t, u IN SHARE ROW EXCLUSIVE MODE NOWAIT');
        self::assertInstanceOf(LockRelationsStatement::class, $statement);
        self::assertInstanceOf(OnlyTableReference::class, $statement->tables[0]);
        self::assertSame(['t', 'u'], [$statement->tables[0]->declaration->name, $statement->tables[1]->declaration->name]);
        self::assertSame(PostgreSqlLockMode::ShareRowExclusive, $statement->mode);
        self::assertTrue($statement->nowait);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->tables, $copy->tables);
        self::assertSame('LOCK TABLE ONLY "public"."t", "public"."u" IN SHARE ROW EXCLUSIVE MODE NOWAIT', $copy->toString());
        self::assertSame($copy->toString(), $binder->bind($copy->toString())->toString());
    }

    public function testWithModeChangesOnlyTheConflictMode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK t');
        self::assertInstanceOf(LockRelationsStatement::class, $statement);
        $changed = $statement->withMode(PostgreSqlLockMode::AccessShare);
        self::assertSame(PostgreSqlLockMode::AccessExclusive, $statement->mode);
        self::assertSame(PostgreSqlLockMode::AccessShare, $changed->mode);
        self::assertFalse($changed->nowait);
        self::assertSame('LOCK TABLE "public"."t" IN ACCESS SHARE MODE', $changed->toString());
    }

    public function testWithTablesPreservesOnlySelection(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)'));
        $statement = $binder->bind('LOCK t');
        $other = $binder->bind('LOCK ONLY u');
        self::assertInstanceOf(LockRelationsStatement::class, $statement);
        self::assertInstanceOf(LockRelationsStatement::class, $other);
        $changed = $statement->withTables($other->tables);
        self::assertInstanceOf(OnlyTableReference::class, $changed->tables[0]);
        self::assertSame('u', $changed->tables[0]->declaration->name);
        self::assertSame('t', $statement->tables[0]->declaration->name);
    }

    public function testRejectsEmptyTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('LOCK t');
        $this->expectException(InvalidStructure::class);
        new LockRelationsStatement($statement->origin, []);
    }

    public function testRejectsAliasedTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT a.id FROM t AS a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $statement->from);
        $this->expectException(InvalidStructure::class);
        new LockRelationsStatement($statement->origin, [$statement->from]);
    }
}
