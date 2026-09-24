<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Composite\CompositeAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\CreateCompositeTypeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(CreateCompositeTypeStatement::class)]
#[Medium]
final class CreateCompositeTypeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TYPE pair AS (label text COLLATE "C", amounts integer[])');
        self::assertInstanceOf(CreateCompositeTypeStatement::class, $statement);
        self::assertSame('label', $statement->attributes[0]->name);
        self::assertSame('CREATE TYPE "pair" AS ("label" text COLLATE "C", "amounts" integer [])', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindsAnEmptyComposite(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE empty AS ()');
        self::assertInstanceOf(CreateCompositeTypeStatement::class, $statement);
        self::assertSame([], $statement->attributes);
        self::assertSame('CREATE TYPE "empty" AS ()', $statement->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (a text)');
        self::assertInstanceOf(CreateCompositeTypeStatement::class, $statement);
        self::assertSame($statement->toString(), $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (a text)');
        self::assertInstanceOf(CreateCompositeTypeStatement::class, $statement);
        self::assertSame('CREATE TYPE "s"."p" AS ("a" text)', $statement->withName(new QualifiedName(['s', 'p']))->toString());
    }

    public function testWithAttributesReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (a text)');
        self::assertInstanceOf(CreateCompositeTypeStatement::class, $statement);
        $changed = $statement->withAttributes([new CompositeAttribute('b', TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'))]);
        self::assertSame('CREATE TYPE "pair" AS ("b" integer)', $changed->toString());
        self::assertCount(1, $statement->attributes);
    }

    public function testRejectsARepeatedAttributeName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE pair AS (a text)');
        self::assertInstanceOf(CreateCompositeTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withAttributes([$statement->attributes[0], $statement->attributes[0]]);
    }
}
