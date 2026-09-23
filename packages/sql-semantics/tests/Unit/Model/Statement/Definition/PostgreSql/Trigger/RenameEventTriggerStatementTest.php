<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\RenameEventTriggerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RenameEventTriggerStatement::class)]
#[Medium]
final class RenameEventTriggerStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalRequestAndQuotesTheNewTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        $changed = $statement->withName('x"y');
        self::assertSame('audit', $statement->name);
        self::assertSame('x"y', $changed->name);
        self::assertStringStartsWith('ALTER EVENT TRIGGER "x""y"', $changed->toString());
    }

    public function testWithNameRejectsAnEmptyTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheOperationAndItsRequiredChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->kind, $copy->kind);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNewNameKeepsTheTargetDistinctFromTheReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        $changed = $statement->withNewName('new"name');
        self::assertSame('audit_ddl', $statement->newName);
        self::assertSame('new"name', $changed->newName);
        self::assertSame('audit', $changed->name);
        self::assertSame('ALTER EVENT TRIGGER "audit" RENAME TO "new""name"', $changed->toString());
    }

    public function testWithNewNameRejectsAnEmptyReplacement(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit RENAME TO audit_ddl');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNewName('');
    }

}
