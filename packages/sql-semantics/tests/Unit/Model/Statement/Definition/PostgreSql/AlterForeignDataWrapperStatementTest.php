<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\FunctionChange;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignDataWrapperStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterForeignDataWrapperStatement::class)]
#[Medium]
final class AlterForeignDataWrapperStatementTest extends TestCase
{
    public function testWithNameQuotesReplacementIdentifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $changed = $statement->withName('other"wrapper');
        self::assertSame('fdw', $statement->name);
        self::assertSame('other"wrapper', $changed->name);
        self::assertStringContainsString('"other""wrapper"', $changed->toString());
        self::assertNotSame($statement, $changed);
    }

    public function testWithNameRejectsAnEmptyIdentity(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->name, $copy->name);
        self::assertSame($statement->handler, $copy->handler);
        self::assertSame($statement->validator, $copy->validator);
        self::assertSame($statement->options, $copy->options);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithChangesReplacesTheCompleteOperationAtomically(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $changed = $statement->withChanges(FunctionChange::Keep, new QualifiedName(['app', 'validate']), [new DropForeignOption('old')]);
        self::assertSame(FunctionChange::Remove, $statement->handler);
        self::assertSame(FunctionChange::Keep, $changed->handler);
        self::assertInstanceOf(QualifiedName::class, $changed->validator);
        self::assertSame(['app', 'validate'], $changed->validator->parts);
        self::assertInstanceOf(DropForeignOption::class, $changed->options[0]);
        self::assertSame('old', $changed->options[0]->name);
    }

    public function testWithChangesRejectsAnEmptyRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withChanges(FunctionChange::Keep, FunctionChange::Keep, []);
    }

    public function testWithChangesAcceptsAnOptionOnlyRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER FOREIGN DATA WRAPPER fdw NO HANDLER');
        self::assertInstanceOf(AlterForeignDataWrapperStatement::class, $statement);
        $changed = $statement->withChanges(FunctionChange::Keep, FunctionChange::Keep, [new DropForeignOption('old')]);
        self::assertSame(FunctionChange::Keep, $changed->handler);
        self::assertSame(FunctionChange::Keep, $changed->validator);
        self::assertSame('ALTER FOREIGN DATA WRAPPER "fdw" OPTIONS(DROP "old")', $changed->toString());
    }

}
