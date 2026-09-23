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
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignServerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateForeignServerStatement::class)]
#[Medium]
final class CreateForeignServerStatementTest extends TestCase
{
    public function testWithOriginRetainsTheConcreteOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->kind, $copy->kind);
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNamePreservesOperandsAndRemovesOriginalFormatting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw /* original */');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $changed = $statement->withName('with"quote');
        self::assertSame('remote', $statement->name);
        self::assertSame('with"quote', $changed->name);
        self::assertSame($statement->options, $changed->options);
        self::assertStringContainsString('"with""quote"', $changed->toString());
        self::assertStringNotContainsString('original', $changed->toString());
    }

    public function testWithNameRejectsEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithDefinitionReplacesWrapperDependentMetadataTogether(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $version = Expression::literal('new', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $version);
        $changed = $statement->withDefinition('newfdw', $version, $version, [new ForeignOption('host', $version)]);
        self::assertSame('fdw', $statement->wrapper);
        self::assertNull($statement->version);
        self::assertSame([], $statement->options);
        self::assertSame('newfdw', $changed->wrapper);
        self::assertSame($version->text, $changed->serverType?->text);
        self::assertSame($version->text, $changed->version?->text);
        self::assertSame('host', $changed->options[0]->name);
    }

    public function testWithDefinitionRejectsDuplicateInitialOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $value = Expression::literal('localhost', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $option = new ForeignOption('host', $value);
        $this->expectException(InvalidStructure::class);
        $statement->withDefinition('fdw', null, null, [$option, $option]);
    }

    public function testWithIfNotExistsChangesOnlyTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE SERVER remote FOREIGN DATA WRAPPER fdw');
        self::assertInstanceOf(CreateForeignServerStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertTrue($changed->ifNotExists);
        self::assertSame('fdw', $changed->wrapper);
    }
}
