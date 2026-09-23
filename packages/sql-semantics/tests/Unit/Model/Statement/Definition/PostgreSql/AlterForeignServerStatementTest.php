<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ServerVersionChange;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterForeignServerStatement::class)]
#[Medium]
final class AlterForeignServerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheConcreteOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->kind, $copy->kind);
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNamePreservesOperandsAndRemovesOriginalFormatting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL /* original */');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $changed = $statement->withName('with"quote');
        self::assertSame('remote', $statement->name);
        self::assertSame('with"quote', $changed->name);
        self::assertSame($statement->options, $changed->options);
        self::assertStringContainsString('"with""quote"', $changed->toString());
        self::assertStringNotContainsString('original', $changed->toString());
    }

    public function testWithNameRejectsEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithChangesKeepsOptionActionsOrderedAndDistinct(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $value = Expression::literal('localhost', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withChanges(ServerVersionChange::Keep, [new DropForeignOption('host'), new AddForeignOption(new ForeignOption('host', $value))]);
        self::assertSame(ServerVersionChange::Remove, $statement->version);
        self::assertSame([], $statement->options);
        self::assertSame(ServerVersionChange::Keep, $changed->version);
        self::assertInstanceOf(DropForeignOption::class, $changed->options[0]);
        self::assertInstanceOf(AddForeignOption::class, $changed->options[1]);
        self::assertSame('host', $changed->options[0]->name);
        self::assertSame('host', $changed->options[1]->option->name);
    }

    public function testWithChangesReplacesVersionWithText(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $value = Expression::literal('v2', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withChanges($value, []);
        self::assertInstanceOf(Literal::class, $changed->version);
        self::assertSame($value->text, $changed->version->text);
        self::assertSame(ServerVersionChange::Remove, $statement->version);
    }

    public function testWithChangesRejectsAnEmptyAlteration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER SERVER remote VERSION NULL');
        self::assertInstanceOf(AlterForeignServerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withChanges(ServerVersionChange::Keep, []);
    }
}
