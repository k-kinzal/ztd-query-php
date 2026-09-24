<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CatalogInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CatalogInvariant::class)]
#[Medium]
final class CatalogInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql])]
    #[TestWith([Dialect::Sqlite])]
    public function testDialectRejectsAnotherDatabaseLanguage(Dialect $dialect): void
    {
        CatalogInvariant::dialect((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')->origin);
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::dialect((new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin);
    }

    public function testTextRequiresAPostgreSqlTextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("COMMENT ON SCHEMA app IS 'x'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, $statement);
        $text = $statement->comment;
        $mysql = \SqlSemantics\Model\Expression::literal('x', Dialect::MySql);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $text);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $mysql);
        CatalogInvariant::text(null);
        CatalogInvariant::text($text);
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::text($mysql);
    }

    public function testCommentRejectsATypeAddressedByName(): void
    {
        CatalogInvariant::comment(new Catalog\DeclaredTypeIdentity(Kind\TypeKind::Type, \SqlSemantics\Type\TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')));
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::comment(new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['mood'])));
    }

    public function testLabelRejectsAnAccessMethod(): void
    {
        CatalogInvariant::label(new Catalog\NamedIdentity(Kind\NamedObjectKind::Role, 'alice'));
        CatalogInvariant::label(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'id', new QualifiedName(['t'])));
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::label(new Catalog\NamedIdentity(Kind\NamedObjectKind::AccessMethod, 'heap'));
    }

    public function testRenameRejectsAnExtension(): void
    {
        CatalogInvariant::rename(new Catalog\OperatorSetIdentity(Kind\OperatorSetKind::OperatorClass, new QualifiedName(['c']), 'btree'));
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::rename(new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'postgis'));
    }

    public function testSchemaRejectsADatabaseWideObject(): void
    {
        CatalogInvariant::schema(new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'postgis'));
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::schema(new Catalog\NamedIdentity(Kind\NamedObjectKind::Language, 'plpgsql'));
    }

    public function testOwnerRejectsATextSearchParser(): void
    {
        CatalogInvariant::owner(new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::TextSearchDictionary, new QualifiedName(['d'])));
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::owner(new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::TextSearchParser, new QualifiedName(['p'])));
    }

    public function testDependencyRejectsAPolicy(): void
    {
        CatalogInvariant::dependency(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t'])));
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::dependency(new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Policy, 'p', new QualifiedName(['t'])));
    }
}
