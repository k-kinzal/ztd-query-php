<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\TypeDefinitions;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TypeDefinitions::class)]
#[Medium]
final class TypeDefinitionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE TYPE t', Statement\CreateShellTypeStatement::class])]
    #[TestWith(['CREATE TYPE enum AS (a text)', Statement\CreateCompositeTypeStatement::class])]
    #[TestWith(['CREATE TYPE t AS ()', Statement\CreateCompositeTypeStatement::class])]
    #[TestWith(['CREATE TYPE t AS ENUM ()', Statement\CreateEnumTypeStatement::class])]
    public function testCreateClassifiesEachForm(string $sql, string $class): void
    {
        self::assertInstanceOf($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql));
    }

    public function testTailSkipsTheCommandAndName(): void
    {
        $tree = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE TYPE s.t AS ENUM ()'), ['DefineStmt'])[0];
        self::assertSame(['AS', 'ENUM', '(', ')'], TypeDefinitions::tail($tree, 2));
    }

    #[TestWith(['CREATE TYPE t AS (a integer, a text)'])]
    #[TestWith(['CREATE TYPE t AS (a SETOF integer)'])]
    #[TestWith(['ALTER TYPE t ADD ATTRIBUTE a SETOF integer'])]
    #[TestWith(['ALTER TYPE t ADD ATTRIBUTE a integer, ADD ATTRIBUTE a text'])]
    public function testAttributesRejectsInvalidDeclarations(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CompositeAttribute->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testAttributeReadsTheCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t AS ("A" text COLLATE s."C")');
        self::assertInstanceOf(Statement\CreateCompositeTypeStatement::class, $statement);
        self::assertSame('A', $statement->attributes[0]->name);
        self::assertSame(['s', 'C'], $statement->attributes[0]->collation?->parts);
    }

    #[TestWith(["CREATE TYPE t AS ENUM ('a', 'a')"])]
    #[TestWith(["ALTER TYPE t ADD VALUE 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'"])]
    #[TestWith(["ALTER TYPE t RENAME VALUE 'a' TO 'a'"])]
    #[TestWith(["ALTER TYPE t DROP VALUE 'a'"])]
    public function testEnumRejectsInvalidLabels(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::EnumLabel->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testAlterEnumReadsTheLabels(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE rename ADD VALUE E'x\\ty'");
        self::assertInstanceOf(Statement\AddEnumLabelStatement::class, $statement);
        self::assertSame("x\ty", $statement->label);
        self::assertNull($statement->position);
        self::assertFalse($statement->ifNotExists);
    }

    public function testPositionReadsThePlacement(): void
    {
        self::assertEquals(new Enumeration\EnumLabelPosition(Enumeration\EnumLabelPlacement::Before, 'b'), TypeDefinitions::position(['ADD', 'VALUE', "'A'", 'BEFORE', "'B'"], ['a', 'b']));
        self::assertNull(TypeDefinitions::position(['ADD', 'VALUE', "'A'"], ['a']));
    }

    public function testAlterCompositeReadsEveryCommand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t DROP ATTRIBUTE a CASCADE, ALTER ATTRIBUTE b TYPE integer');
        self::assertInstanceOf(Statement\AlterCompositeTypeStatement::class, $statement);
        self::assertEquals(new Composite\DropAttribute('a', false, DropBehavior::Cascade), $statement->changes[0]);
        self::assertInstanceOf(Composite\RetypeAttribute::class, $statement->changes[1]);
    }

    public function testChangeReadsAnAddedAttribute(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t ADD ATTRIBUTE a integer RESTRICT');
        self::assertInstanceOf(Statement\AlterCompositeTypeStatement::class, $statement);
        self::assertInstanceOf(Composite\AddAttribute::class, $statement->changes[0]);
        self::assertSame(DropBehavior::Restrict, $statement->changes[0]->behavior);
    }

    public function testNameRejectsAnOverQualifiedType(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE a.b.c');
    }

    public function testCollationIsOptional(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE t ALTER ATTRIBUTE b TYPE integer');
        self::assertInstanceOf(Statement\AlterCompositeTypeStatement::class, $statement);
        self::assertInstanceOf(Composite\RetypeAttribute::class, $statement->changes[0]);
        self::assertNull($statement->changes[0]->collation);
    }

    public function testLabelDecodesDollarQuotedText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE t AS ENUM ($q$a\'b$q$)');
        self::assertInstanceOf(Statement\CreateEnumTypeStatement::class, $statement);
        self::assertSame(["a'b"], $statement->labels);
    }

    public function testSetOfDetectsTheMarker(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('DROP TYPE SETOF json, integer');
        $types = Tree::outer($tree, ['Typename']);
        self::assertTrue(TypeDefinitions::setOf($types[0]));
        self::assertFalse(TypeDefinitions::setOf($types[1]));
    }

    #[TestWith(['CREATE TYPE r AS RANGE (subtype = int4)', 'CREATE TYPE "r" AS RANGE(SUBTYPE = integer)'])]
    #[TestWith(['CREATE TYPE b (input = f, output = g)', 'CREATE TYPE "b"(INPUT = "f", OUTPUT = "g")'])]
    #[TestWith(['CREATE TYPE s.c AS (a int, b text COLLATE "C")', 'CREATE TYPE "s"."c" AS ("a" integer, "b" text COLLATE "C")'])]
    #[TestWith(["ALTER TYPE e RENAME VALUE 'a' TO 'b'", "ALTER TYPE \"e\" RENAME VALUE 'a' TO 'b'"])]
    #[TestWith(["ALTER TYPE e ADD VALUE IF NOT EXISTS 'x' BEFORE 'a'", "ALTER TYPE \"e\" ADD VALUE IF NOT EXISTS 'x' BEFORE 'a'"])]
    #[TestWith(['ALTER TYPE c DROP ATTRIBUTE IF EXISTS a', 'ALTER TYPE "c" DROP ATTRIBUTE IF EXISTS "a"'])]
    #[TestWith(['ALTER TYPE c ALTER ATTRIBUTE a SET DATA TYPE text COLLATE s.c', 'ALTER TYPE "c" ALTER ATTRIBUTE "a" TYPE text COLLATE "s"."c"'])]
    public function testCreateAndAlterWriteBackEveryForm(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    public function testCollationRejectsAnOverQualifiedName(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE c AS (a int COLLATE a.b.c)');
    }

    public function testNameAndAttributesReadTheDefinition(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), ''));
        $tree = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('CREATE TYPE s.c AS (a int, b text COLLATE s."C")'), ['CompositeTypeStmt', 'DefineStmt'])[0];
        $elements = Tree::outer($tree, ['TableFuncElement']);
        self::assertSame(['s', 'c'], TypeDefinitions::name($tree, $context)->parts);
        self::assertSame(['a', 'b'], array_map(static fn (Composite\CompositeAttribute $attribute): string => $attribute->name, TypeDefinitions::attributes($tree, $context)));
        self::assertSame('b', TypeDefinitions::attribute($elements[1], $context)->name);
        self::assertSame(['s', 'C'], TypeDefinitions::collation($elements[1], $context)?->parts);
    }

    public function testEnumAndLabelReadTheStringConstants(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), ''));
        $tree = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse("CREATE TYPE e AS ENUM ('a', 'b')"), ['CreateEnumStmt', 'DefineStmt'])[0];
        $statement = TypeDefinitions::enum(new Origin('s0', $tree, Dialect::PostgreSql), new QualifiedName(['e']), Tree::child($tree, ['opt_enum_val_list']), $tree, $context);
        self::assertSame(['a', 'b'], $statement->labels);
        self::assertSame('b', TypeDefinitions::label(Tree::outer($tree, ['Sconst'])[1], $context));
    }

    public function testChangeReadsOneCommand(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), ''));
        $command = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('ALTER TYPE c DROP ATTRIBUTE IF EXISTS a'), ['alter_type_cmd'])[0];
        self::assertEquals(new Composite\DropAttribute('a', true, DropBehavior::Default), TypeDefinitions::change($command, $context));
    }
}
