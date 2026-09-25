<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignDataWrapperStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateForeignDataWrapperStatement::class)]
#[Medium]
final class CreateForeignDataWrapperStatementTest extends TestCase
{
    public function testWithNameQuotesReplacementIdentifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $changed = $statement->withName('other"wrapper');
        self::assertSame('fdw', $statement->name);
        self::assertSame('other"wrapper', $changed->name);
        self::assertStringContainsString('"other""wrapper"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertNotSame($statement, $changed);
    }

    public function testWithNameRejectsAnEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->handler, $copy->handler);
        self::assertSame($statement->validator, $copy->validator);
        self::assertSame($statement->options, $copy->options);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithFunctionsInstallsAndRemovesOptionalSupportFunctions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $changed = $statement->withFunctions(new QualifiedName(['app', 'handler']), new QualifiedName(['app', 'validator']));
        self::assertNull($statement->handler);
        self::assertNotNull($changed->handler);
        self::assertSame(['app', 'handler'], $changed->handler->parts);
        self::assertNotNull($changed->validator);
        self::assertSame(['app', 'validator'], $changed->validator->parts);
        $removed = $changed->withFunctions(null, null);
        self::assertNull($removed->handler);
        self::assertNull($removed->validator);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($removed));
    }

    public function testWithOptionsKeepsTheOriginalInitialOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $value = Expression::literal('csv', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new ForeignOption('format', $value)]);
        self::assertSame([], $statement->options);
        self::assertSame('format', $changed->options[0]->name);
        self::assertSame("'csv'", $changed->options[0]->value->text);
    }

    public function testWithOptionsRejectsDuplicateNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignDataWrapperStatement::class, $statement);
        $value = Expression::literal('csv', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $this->expectException(InvalidStructure::class);
        $statement->withOptions([new ForeignOption('format', $value), new ForeignOption('format', $value)]);
    }

}
