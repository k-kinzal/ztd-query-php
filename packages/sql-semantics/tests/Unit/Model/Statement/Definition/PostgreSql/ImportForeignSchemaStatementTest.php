<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\AllForeignTables;
use SqlSemantics\Model\Definition\Foreign\ExcludeForeignTables;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignRelation;
use SqlSemantics\Model\Definition\Foreign\ImportOnlyTables;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\PostgreSql\ImportForeignSchemaStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ImportForeignSchemaStatement::class)]
#[Medium]
final class ImportForeignSchemaStatementTest extends TestCase
{
    public function testWithRemoteReplacesPairedSourceNamesImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $changed = $statement->withRemote('remote"server', 'ext"schema');
        self::assertSame('remote', $statement->server);
        self::assertSame('ext', $statement->remoteSchema);
        self::assertSame('remote"server', $changed->server);
        self::assertSame('ext"schema', $changed->remoteSchema);
        self::assertSame('app', $changed->localSchema);
        self::assertNotSame($statement, $changed);
    }

    public function testWithLocalSchemaQuotesTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $changed = $statement->withLocalSchema('app"; DROP SCHEMA ext; --');
        self::assertSame('app', $statement->localSchema);
        self::assertSame('app"; DROP SCHEMA ext; --', $changed->localSchema);
        self::assertStringContainsString('"app""; DROP SCHEMA ext; --"', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithSelectionChangesBetweenExplicitSelectionForms(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $only = $statement->withSelection(new ImportOnlyTables([new ForeignRelation(new QualifiedName(['users']))]));
        $except = $only->withSelection(new ExcludeForeignTables([new ForeignRelation(new QualifiedName(['private']))]));
        $all = $except->withSelection(AllForeignTables::InSchema);
        self::assertInstanceOf(ImportOnlyTables::class, $only->selection);
        self::assertSame(['users'], $only->selection->tables[0]->name->parts);
        self::assertInstanceOf(ExcludeForeignTables::class, $except->selection);
        self::assertSame(['private'], $except->selection->tables[0]->name->parts);
        self::assertSame(AllForeignTables::InSchema, $all->selection);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($all));
    }

    public function testWithOptionsRefreshesLiteralFactsAndPreservesTheOriginal(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $value = Expression::literal('true', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $value);
        $changed = $statement->withOptions([new ForeignOption('import_default', $value)]);
        self::assertSame([], $statement->options);
        self::assertSame('import_default', $changed->options[0]->name);
        self::assertSame($value->text, $changed->options[0]->value->text);
        self::assertSame([], $changed->withOptions([])->options);
    }

    public function testWithOriginRetainsAllOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->selection, $copy->selection);
        self::assertSame($statement->remoteSchema, $copy->remoteSchema);
        self::assertSame($statement->server, $copy->server);
        self::assertSame($statement->localSchema, $copy->localSchema);
        self::assertSame($statement->options, $copy->options);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithRemoteRejectsAnEmptyServerName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRemote('', 'ext');
    }

    public function testWithRemoteRejectsAnEmptySchemaName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withRemote('remote', '');
    }

    public function testWithLocalSchemaRejectsAnEmptyName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('IMPORT FOREIGN SCHEMA ext FROM SERVER remote INTO app');
        self::assertInstanceOf(ImportForeignSchemaStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withLocalSchema('');
    }


}
