<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\DropEventTriggersStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DropEventTriggersStatement::class)]
#[Medium]
final class DropEventTriggersStatementTest extends TestCase
{
    public function testWithOriginRetainsTheConcreteOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER one, two');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->kind, $copy->kind);
    }

    public function testWithOriginRejectsAnIncompatibleLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER one, two');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNamesChangesTheCompleteSelectionAndQuotesEachIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER one, two');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        $changed = $statement->withNames(['x"y', 'with.dot']);
        self::assertSame(['one', 'two'], $statement->names);
        self::assertSame(['x"y', 'with.dot'], $changed->names);
        self::assertStringContainsString('"x""y", "with.dot"', $changed->toString());
    }

    public function testWithNamesRejectsEmptyIdentifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER one, two');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withNames(['']);
    }

    public function testWithIfExistsChangesMissingObjectBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER one, two');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertTrue($changed->ifExists);
        self::assertSame($statement->names, $changed->names);
    }

    public function testWithBehaviorRetainsTargetsWhileChangingDependencyHandling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER one, two');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        $changed = $statement->withBehavior(DropBehavior::Cascade);
        self::assertSame(DropBehavior::Default, $statement->behavior);
        self::assertSame(DropBehavior::Cascade, $changed->behavior);
        self::assertSame($statement->names, $changed->names);
    }

    public function testIfExistsDefaultsToOff(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER IF EXISTS e', strict: false);
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        self::assertFalse((new DropEventTriggersStatement($statement->origin, $statement->names))->ifExists);
    }
}
