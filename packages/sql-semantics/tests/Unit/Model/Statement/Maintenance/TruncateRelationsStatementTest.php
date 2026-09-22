<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IdentityReset;
use SqlSemantics\Model\Maintenance\ReferencingTables;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Maintenance\TruncateRelationsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TruncateRelationsStatement::class)]
#[Medium]
final class TruncateRelationsStatementTest extends TestCase
{
    public function testWithOriginRetainsExplicitTargetsAndPolicies(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('TRUNCATE ONLY(t), u RESTART IDENTITY CASCADE');
        self::assertInstanceOf(TruncateRelationsStatement::class, $statement);
        self::assertInstanceOf(OnlyTableReference::class, $statement->tables[0]);
        self::assertInstanceOf(TableReference::class, $statement->tables[1]);
        self::assertSame(IdentityReset::Restart, $statement->identities);
        self::assertSame(ReferencingTables::Include, $statement->references);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->tables, $copy->tables);
        self::assertSame('TRUNCATE TABLE ONLY "public"."t", "public"."u" RESTART IDENTITY CASCADE', $copy->toString());
        self::assertSame($copy->toString(), $binder->bind($copy->toString())->toString());
    }

    public function testWithIdentitiesChangesOnlySequencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('TRUNCATE t');
        self::assertInstanceOf(TruncateRelationsStatement::class, $statement);
        $changed = $statement->withIdentities(IdentityReset::Restart);
        self::assertSame(IdentityReset::Continue, $statement->identities);
        self::assertSame(IdentityReset::Restart, $changed->identities);
        self::assertSame(ReferencingTables::RequireListed, $changed->references);
        self::assertSame('TRUNCATE TABLE "public"."t" RESTART IDENTITY RESTRICT', $changed->toString());
    }

    public function testWithTablesPreservesReferencePolicyAndDescendantSelection(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)'));
        $statement = $binder->bind('TRUNCATE t CASCADE');
        $other = $binder->bind('TRUNCATE ONLY u');
        self::assertInstanceOf(TruncateRelationsStatement::class, $statement);
        self::assertInstanceOf(TruncateRelationsStatement::class, $other);
        $changed = $statement->withTables($other->tables);
        self::assertSame(ReferencingTables::Include, $changed->references);
        self::assertInstanceOf(OnlyTableReference::class, $changed->tables[0]);
        self::assertSame('t', $statement->tables[0]->declaration->name);
        self::assertSame('u', $changed->tables[0]->declaration->name);
    }

    public function testRejectsEmptyTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('TRUNCATE t');
        $this->expectException(InvalidStructure::class);
        new TruncateRelationsStatement($statement->origin, []);
    }
}
