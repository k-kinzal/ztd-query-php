<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateForeignTableStatement::class)]
#[Medium]
final class CreateForeignTableStatementTest extends TestCase
{
    public function testBindsTheOperandsAndWritesThemBack(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        self::assertSame('ft', $statement->definition->table->name);
        self::assertSame('remote', $statement->server);
        self::assertTrue($statement->ifNotExists);
        self::assertSame(['p'], $statement->inherits[0]->parts);
        self::assertSame('schema_name', $statement->options[0]->name);
        self::assertSame('CREATE FOREIGN TABLE IF NOT EXISTS "app"."ft"("a" integer NOT NULL, CHECK (("a" > 0))) INHERITS("p") SERVER "remote" OPTIONS("schema_name" \'x\')', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithServerReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        $changed = $statement->withServer('other');
        self::assertNotSame($statement, $changed);
        self::assertEquals('remote', $statement->server);
        self::assertEquals('other', $changed->server);
        self::assertStringContainsString('SERVER "other"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOptionsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        $changed = $statement->withOptions([]);
        self::assertNotSame($statement, $changed);
        self::assertEquals($statement->options, $statement->options);
        self::assertEquals([], $changed->options);
        self::assertStringContainsString('SERVER "remote"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithInheritsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        $changed = $statement->withInherits([new QualifiedName(['q'])]);
        self::assertNotSame($statement, $changed);
        self::assertEquals([new QualifiedName(['p'])], $statement->inherits);
        self::assertEquals([new QualifiedName(['q'])], $changed->inherits);
        self::assertStringContainsString('INHERITS("q")', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithIfNotExistsReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE IF NOT EXISTS app.ft (a integer NOT NULL, CHECK (a > 0)) INHERITS (p) SERVER remote OPTIONS (schema_name 'x')", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        $changed = $statement->withIfNotExists(false);
        self::assertNotSame($statement, $changed);
        self::assertEquals(true, $statement->ifNotExists);
        self::assertEquals(false, $changed->ifNotExists);
        self::assertStringContainsString('CREATE FOREIGN TABLE "app"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testBindsTemplatesAndColumnOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE ft (a integer OPTIONS (column_name 'remote_a'), LIKE app.t INCLUDING DEFAULTS) SERVER s", strict: false);
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        self::assertSame('a', $statement->columnOptions[0]->column);
        self::assertSame(['app', 't'], $statement->templates[0]->source->parts);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), strict: false)->toString());
    }
}
