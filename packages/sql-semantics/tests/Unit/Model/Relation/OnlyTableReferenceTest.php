<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Statement\TableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OnlyTableReference::class)]
#[Medium]
final class OnlyTableReferenceTest extends TestCase
{
    public function testWithScopePreservesExcludedDescendantsAndAlias(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('SELECT a.id FROM ONLY(t) AS a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $table = $statement->from;
        self::assertInstanceOf(OnlyTableReference::class, $table);
        $copy = $table->withScope('new_scope');
        self::assertSame('new_scope', $copy->scopeId);
        self::assertSame($table->declaration, $copy->declaration);
        self::assertSame('a', $copy->alias);
        self::assertSame(['public', 't'], $copy->name->parts);
        self::assertNotSame($copy->scopeId, $table->scopeId);
        self::assertSame('SELECT "a"."id" AS "id" FROM ONLY "public"."t" AS "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithScopeRetainsUnresolvedTableIdentity(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('TABLE ONLY absent', strict: false);
        self::assertInstanceOf(TableStatement::class, $statement);
        self::assertInstanceOf(OnlyTableReference::class, $statement->from);
        self::assertFalse($statement->from->declaration->resolved);
        self::assertSame('TABLE ONLY "public"."absent"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsForeignDialectDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('TABLE t');
        self::assertInstanceOf(TableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new OnlyTableReference('r', 's', $statement->from->declaration, new \SqlSemantics\Model\Relation\QualifiedName(['t']), null, $statement->source);
    }
}
