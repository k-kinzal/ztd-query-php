<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\TypeDefinitions;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(TypeDefinitions::class)]
#[Medium]
final class TypeDefinitionsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(TypeDefinitions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['CREATE TYPE "t"'])]
    #[TestWith(['CREATE TYPE "t" AS ("a" integer)'])]
    #[TestWith(["CREATE TYPE \"t\" AS ENUM('a')"])]
    #[TestWith(["ALTER TYPE \"t\" ADD VALUE 'a' AFTER 'b'"])]
    #[TestWith(["ALTER TYPE \"t\" RENAME VALUE 'a' TO 'b'"])]
    #[TestWith(['ALTER TYPE "t" DROP ATTRIBUTE "a"'])]
    #[TestWith(['CREATE TYPE "t"(INPUT = "i", OUTPUT = "o")'])]
    #[TestWith(['CREATE TYPE "r" AS RANGE(SUBTYPE = integer)'])]
    #[TestWith(['ALTER TYPE "t" SET (SEND = NONE)'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        self::assertSame($sql, TypeDefinitions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testAttributeWritesTheCollation(): void
    {
        self::assertSame('"a" text COLLATE "C"', TypeDefinitions::attribute(new Composite\CompositeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), new QualifiedName(['C'])))->toString());
    }

    public function testChangeWritesEachCommand(): void
    {
        self::assertSame('ALTER ATTRIBUTE "a" TYPE text CASCADE', TypeDefinitions::change(new Composite\RetypeAttribute('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'), null, DropBehavior::Cascade))->toString());
        self::assertSame('DROP ATTRIBUTE IF EXISTS "a"', TypeDefinitions::change(new Composite\DropAttribute('a', true))->toString());
    }

    public function testCollationIsEmptyWithoutAName(): void
    {
        self::assertSame([], TypeDefinitions::collation(null));
    }

    public function testTextEscapesQuotes(): void
    {
        self::assertSame("'it''s'", TypeDefinitions::text("it's")->toString());
    }

    public function testAlterWritesTheTarget(): void
    {
        self::assertSame('ALTER TYPE "s"."t"', TypeDefinitions::alter(new QualifiedName(['s', 't']))->toString());
    }
}
