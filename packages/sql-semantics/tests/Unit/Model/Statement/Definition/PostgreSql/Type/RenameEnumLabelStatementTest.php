<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Type\RenameEnumLabelStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameEnumLabelStatement::class)]
#[Medium]
final class RenameEnumLabelStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
        self::assertInstanceOf(RenameEnumLabelStatement::class, $statement);
        self::assertSame('ok', $statement->label);
        self::assertSame('fine', $statement->newLabel);
        self::assertSame("ALTER TYPE \"mood\" RENAME VALUE 'ok' TO 'fine'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
        self::assertInstanceOf(RenameEnumLabelStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithTypeReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
        self::assertInstanceOf(RenameEnumLabelStatement::class, $statement);
        self::assertSame("ALTER TYPE \"s\".\"m\" RENAME VALUE 'ok' TO 'fine'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withType(new QualifiedName(['s', 'm']))));
    }

    public function testWithLabelReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
        self::assertInstanceOf(RenameEnumLabelStatement::class, $statement);
        self::assertSame('good', $statement->withLabel('good')->label);
    }

    public function testWithNewLabelReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
        self::assertInstanceOf(RenameEnumLabelStatement::class, $statement);
        self::assertSame('great', $statement->withNewLabel('great')->newLabel);
    }

    public function testRejectsARenameToTheSameLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("ALTER TYPE mood RENAME VALUE 'ok' TO 'fine'");
        self::assertInstanceOf(RenameEnumLabelStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNewLabel('ok');
    }
}
