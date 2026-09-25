<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionOption;
use SqlSemantics\Model\Definition\TypeSystem\Definition\RangeAttribute;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\CreateRangeTypeStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateRangeTypeStatement::class)]
#[Medium]
final class CreateRangeTypeStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TYPE r AS RANGE (subtype = text, collation = "C", subtype_opclass = s.text_ops)');
        self::assertInstanceOf(CreateRangeTypeStatement::class, $statement);
        self::assertSame(RangeAttribute::Collation, $statement->options[1]->attribute);
        self::assertSame('CREATE TYPE "r" AS RANGE(SUBTYPE = text, COLLATION = "C", SUBTYPE_OPCLASS = "s"."text_ops")', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRejectsARangeWithoutSubtype(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE r AS RANGE (subtype = text)');
        self::assertInstanceOf(CreateRangeTypeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new DefinitionOption(RangeAttribute::Canonical, new QualifiedName(['f']))]);
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE r AS RANGE (subtype = text)');
        self::assertInstanceOf(CreateRangeTypeStatement::class, $statement);
        self::assertSame('CREATE TYPE "r" AS RANGE(SUBTYPE = text)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithNameReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE r AS RANGE (subtype = text)');
        self::assertInstanceOf(CreateRangeTypeStatement::class, $statement);
        self::assertSame('CREATE TYPE "s"."q" AS RANGE(SUBTYPE = text)', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withName(new QualifiedName(['s', 'q']))));
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TYPE r AS RANGE (subtype = text)');
        self::assertInstanceOf(CreateRangeTypeStatement::class, $statement);
        self::assertCount(2, $statement->withOptions([...$statement->options, new DefinitionOption(RangeAttribute::SubtypeDiff, new QualifiedName(['d']))])->options);
        self::assertCount(1, $statement->options);
    }
}
