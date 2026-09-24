<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPlacement;
use SqlSemantics\Model\Definition\TypeSystem\Enumeration\EnumLabelPosition;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\AddEnumLabelStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AddEnumLabelStatement::class)]
#[Medium]
final class AddEnumLabelStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("ALTER TYPE app.mood ADD VALUE IF NOT EXISTS U&'d\\0061t' AFTER 'sad'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        self::assertSame('dat', $statement->label);
        self::assertTrue($statement->ifNotExists);
        self::assertEquals(new EnumLabelPosition(EnumLabelPlacement::After, 'sad'), $statement->position);
        self::assertSame("ALTER TYPE \"app\".\"mood\" ADD VALUE IF NOT EXISTS 'dat' AFTER 'sad'", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'a'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        self::assertSame("ALTER TYPE \"mood\" ADD VALUE 'a'", $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'a'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        self::assertSame("ALTER TYPE \"f\" ADD VALUE 'a'", $statement->withType(new QualifiedName(['f']))->toString());
    }

    public function testWithLabelReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'a'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        self::assertSame("ALTER TYPE \"mood\" ADD VALUE 'b'", $statement->withLabel('b')->toString());
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'a'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        self::assertTrue($statement->withIfNotExists(true)->ifNotExists);
        self::assertFalse($statement->ifNotExists);
    }

    public function testWithPositionReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'a'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        self::assertSame("ALTER TYPE \"mood\" ADD VALUE 'a' BEFORE 'z'", $statement->withPosition(new EnumLabelPosition(EnumLabelPlacement::Before, 'z'))->toString());
    }

    public function testRejectsALabelLongerThanSixtyThreeBytes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood ADD VALUE 'a'");
        self::assertInstanceOf(AddEnumLabelStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withLabel(str_repeat('a', 64));
    }
}
