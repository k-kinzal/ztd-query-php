<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\Composite;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AlterCompositeTypeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(AlterCompositeTypeStatement::class)]
#[Medium]
final class AlterCompositeTypeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER TYPE pair ADD ATTRIBUTE a int COLLATE c CASCADE, DROP ATTRIBUTE IF EXISTS b, ALTER ATTRIBUTE c SET DATA TYPE text COLLATE d RESTRICT');
        self::assertInstanceOf(AlterCompositeTypeStatement::class, $statement);
        self::assertCount(3, $statement->changes);
        self::assertEquals(new Composite\DropAttribute('b', true), $statement->changes[1]);
        self::assertSame('ALTER TYPE "pair" ADD ATTRIBUTE "a" integer COLLATE "c" CASCADE, DROP ATTRIBUTE IF EXISTS "b", ALTER ATTRIBUTE "c" TYPE text COLLATE "d" RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair DROP ATTRIBUTE a');
        self::assertInstanceOf(AlterCompositeTypeStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair DROP ATTRIBUTE a');
        self::assertInstanceOf(AlterCompositeTypeStatement::class, $statement);
        self::assertSame('ALTER TYPE "s"."p" DROP ATTRIBUTE "a"', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withType(new QualifiedName(['s', 'p']))));
    }

    public function testWithChangesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair DROP ATTRIBUTE a');
        self::assertInstanceOf(AlterCompositeTypeStatement::class, $statement);
        $changed = $statement->withChanges([new Composite\AddAttribute(new Composite\CompositeAttribute('b', TypeDescriptor::builtin(Dialect::PostgreSql, 'text')), DropBehavior::Restrict)]);
        self::assertSame('ALTER TYPE "pair" ADD ATTRIBUTE "b" text RESTRICT', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testRejectsAnAttributeAddedTwice(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair ADD ATTRIBUTE a text');
        self::assertInstanceOf(AlterCompositeTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withChanges([$statement->changes[0], $statement->changes[0]]);
    }
}
