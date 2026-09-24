<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\ZeroArgumentAggregate;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CatalogInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

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
        CatalogInvariant::comment(new Catalog\DeclaredTypeIdentity(Kind\TypeKind::Type, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer')));
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

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerLabelAccepted(): array
    {
        return [
            'table' => [new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t']))],
            'materialized view' => [new Catalog\RelationIdentity(Kind\RelationKind::MaterializedView, new QualifiedName(['m']))],
            'role' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Role, 'r')],
            'database' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Database, 'd')],
            'declared type' => [new Catalog\DeclaredTypeIdentity(Kind\TypeKind::Type, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))],
            'aggregate' => [new Catalog\AggregateIdentity(new ZeroArgumentAggregate(new QualifiedName(['a'])))],
            'routine' => [new Catalog\RoutineIdentity(Kind\RoutineKind::Function, new RoutineByName(new QualifiedName(['f'])))],
            'large object' => [new Catalog\LargeObjectIdentity(5)],
            'column' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'c', new QualifiedName(['t']))],
        ];
    }

    #[DataProvider('providerLabelAccepted')]
    public function testLabelAcceptsTheDocumentedObjectClasses(ObjectAddress $object): void
    {
        $this->expectNotToPerformAssertions();
        CatalogInvariant::label($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerLabelRejected(): array
    {
        return [
            'index' => [new Catalog\RelationIdentity(Kind\RelationKind::Index, new QualifiedName(['i']))],
            'access method' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::AccessMethod, 'heap')],
            'extension' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'e')],
            'foreign data wrapper' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::ForeignDataWrapper, 'w')],
            'server' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Server, 's')],
            'type name' => [new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['mood']))],
            'trigger' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t']))],
            'collation' => [new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['c']))],
            'operator' => [new Catalog\OperatorIdentity(new QualifiedName(['+']), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))],
            'cast' => [new Catalog\CastIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))],
        ];
    }

    #[DataProvider('providerLabelRejected')]
    public function testLabelRejectsOtherObjectClasses(ObjectAddress $object): void
    {
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::label($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerRenameAccepted(): array
    {
        return [
            'aggregate' => [new Catalog\AggregateIdentity(new ZeroArgumentAggregate(new QualifiedName(['a'])))],
            'collation' => [new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['c']))],
            'type name' => [new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['mood']))],
            'routine' => [new Catalog\RoutineIdentity(Kind\RoutineKind::Function, new RoutineByName(new QualifiedName(['f'])))],
            'operator class' => [new Catalog\OperatorSetIdentity(Kind\OperatorSetKind::OperatorClass, new QualifiedName(['c']), 'btree')],
            'role' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Role, 'r')],
            'database' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Database, 'd')],
            'server' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Server, 's')],
            'trigger' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t']))],
            'rule' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Rule, 'ru', new QualifiedName(['t']))],
        ];
    }

    #[DataProvider('providerRenameAccepted')]
    public function testRenameAcceptsTheDocumentedObjectClasses(ObjectAddress $object): void
    {
        $this->expectNotToPerformAssertions();
        CatalogInvariant::rename($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerRenameRejected(): array
    {
        return [
            'access method' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::AccessMethod, 'heap')],
            'extension' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'e')],
            'event trigger' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::EventTrigger, 'et')],
            'column' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'c', new QualifiedName(['t']))],
            'policy' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Policy, 'p', new QualifiedName(['t']))],
            'table' => [new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t']))],
            'operator' => [new Catalog\OperatorIdentity(new QualifiedName(['+']), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))],
            'large object' => [new Catalog\LargeObjectIdentity(5)],
            'cast' => [new Catalog\CastIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))],
        ];
    }

    #[DataProvider('providerRenameRejected')]
    public function testRenameRejectsOtherObjectClasses(ObjectAddress $object): void
    {
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::rename($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerSchemaAccepted(): array
    {
        return [
            'aggregate' => [new Catalog\AggregateIdentity(new ZeroArgumentAggregate(new QualifiedName(['a'])))],
            'collation' => [new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['c']))],
            'type name' => [new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['mood']))],
            'routine' => [new Catalog\RoutineIdentity(Kind\RoutineKind::Function, new RoutineByName(new QualifiedName(['f'])))],
            'operator' => [new Catalog\OperatorIdentity(new QualifiedName(['+']), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))],
            'operator class' => [new Catalog\OperatorSetIdentity(Kind\OperatorSetKind::OperatorClass, new QualifiedName(['c']), 'btree')],
            'extension' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'e')],
        ];
    }

    #[DataProvider('providerSchemaAccepted')]
    public function testSchemaAcceptsTheDocumentedObjectClasses(ObjectAddress $object): void
    {
        $this->expectNotToPerformAssertions();
        CatalogInvariant::schema($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerSchemaRejected(): array
    {
        return [
            'role' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Role, 'r')],
            'table' => [new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t']))],
            'large object' => [new Catalog\LargeObjectIdentity(5)],
            'column' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'c', new QualifiedName(['t']))],
            'cast' => [new Catalog\CastIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))],
            'declared type' => [new Catalog\DeclaredTypeIdentity(Kind\TypeKind::Type, TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))],
        ];
    }

    #[DataProvider('providerSchemaRejected')]
    public function testSchemaRejectsOtherObjectClasses(ObjectAddress $object): void
    {
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::schema($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerOwnerAccepted(): array
    {
        return [
            'aggregate' => [new Catalog\AggregateIdentity(new ZeroArgumentAggregate(new QualifiedName(['a'])))],
            'type name' => [new Catalog\TypeNameIdentity(Kind\TypeKind::Type, new QualifiedName(['mood']))],
            'routine' => [new Catalog\RoutineIdentity(Kind\RoutineKind::Function, new RoutineByName(new QualifiedName(['f'])))],
            'large object' => [new Catalog\LargeObjectIdentity(5)],
            'operator' => [new Catalog\OperatorIdentity(new QualifiedName(['+']), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))],
            'operator class' => [new Catalog\OperatorSetIdentity(Kind\OperatorSetKind::OperatorClass, new QualifiedName(['c']), 'btree')],
            'collation' => [new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::Collation, new QualifiedName(['c']))],
            'database' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Database, 'd')],
            'server' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Server, 's')],
        ];
    }

    #[DataProvider('providerOwnerAccepted')]
    public function testOwnerAcceptsTheDocumentedObjectClasses(ObjectAddress $object): void
    {
        $this->expectNotToPerformAssertions();
        CatalogInvariant::owner($object);
    }

    /**
     * @return array<string, array{ObjectAddress}>
     */
    public static function providerOwnerRejected(): array
    {
        return [
            'text search parser' => [new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::TextSearchParser, new QualifiedName(['p']))],
            'text search template' => [new Catalog\SchemaObjectIdentity(Kind\SchemaObjectKind::TextSearchTemplate, new QualifiedName(['p']))],
            'access method' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::AccessMethod, 'heap')],
            'extension' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Extension, 'e')],
            'role' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::Role, 'r')],
            'event trigger' => [new Catalog\NamedIdentity(Kind\NamedObjectKind::EventTrigger, 'et')],
            'table' => [new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t']))],
            'column' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'c', new QualifiedName(['t']))],
            'cast' => [new Catalog\CastIdentity(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'), TypeDescriptor::builtin(Dialect::PostgreSql, 'text'))],
        ];
    }

    #[DataProvider('providerOwnerRejected')]
    public function testOwnerRejectsOtherObjectClasses(ObjectAddress $object): void
    {
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::owner($object);
    }

    /**
     * @return array<string, array{Catalog\RoutineIdentity|Catalog\RelationMemberIdentity|Catalog\RelationIdentity}>
     */
    public static function providerDependencyAccepted(): array
    {
        return [
            'routine' => [new Catalog\RoutineIdentity(Kind\RoutineKind::Function, new RoutineByName(new QualifiedName(['f'])))],
            'trigger' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Trigger, 'tr', new QualifiedName(['t']))],
            'materialized view' => [new Catalog\RelationIdentity(Kind\RelationKind::MaterializedView, new QualifiedName(['m']))],
            'index' => [new Catalog\RelationIdentity(Kind\RelationKind::Index, new QualifiedName(['i']))],
        ];
    }

    #[DataProvider('providerDependencyAccepted')]
    public function testDependencyAcceptsTheDocumentedObjectClasses(Catalog\RoutineIdentity|Catalog\RelationMemberIdentity|Catalog\RelationIdentity $object): void
    {
        $this->expectNotToPerformAssertions();
        CatalogInvariant::dependency($object);
    }

    /**
     * @return array<string, array{Catalog\RoutineIdentity|Catalog\RelationMemberIdentity|Catalog\RelationIdentity}>
     */
    public static function providerDependencyRejected(): array
    {
        return [
            'table' => [new Catalog\RelationIdentity(Kind\RelationKind::Table, new QualifiedName(['t']))],
            'column' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Column, 'c', new QualifiedName(['t']))],
            'policy' => [new Catalog\RelationMemberIdentity(Kind\RelationMemberKind::Policy, 'p', new QualifiedName(['t']))],
        ];
    }

    #[DataProvider('providerDependencyRejected')]
    public function testDependencyRejectsOtherObjectClasses(Catalog\RoutineIdentity|Catalog\RelationMemberIdentity|Catalog\RelationIdentity $object): void
    {
        $this->expectException(InvalidStructure::class);
        CatalogInvariant::dependency($object);
    }
}
