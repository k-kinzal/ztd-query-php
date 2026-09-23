<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\ChangeEventTriggerOwnerStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ChangeEventTriggerOwnerStatement::class)]
#[Medium]
final class ChangeEventTriggerOwnerStatementTest extends TestCase
{
    public function testWithNamePreservesTheOriginalRequestAndQuotesTheNewTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER');
        self::assertInstanceOf(ChangeEventTriggerOwnerStatement::class, $statement);
        $changed = $statement->withName('x"y');
        self::assertSame('audit', $statement->name);
        self::assertSame('x"y', $changed->name);
        self::assertStringStartsWith('ALTER EVENT TRIGGER "x""y"', $changed->toString());
    }

    public function testWithNameRejectsAnEmptyTarget(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER');
        self::assertInstanceOf(ChangeEventTriggerOwnerStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withName('');
    }

    public function testWithOriginRetainsTheOperationAndItsRequiredChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER');
        self::assertInstanceOf(ChangeEventTriggerOwnerStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame($statement->kind, $copy->kind);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER');
        self::assertInstanceOf(ChangeEventTriggerOwnerStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    public function testWithNewOwnerKeepsExplicitNamesDistinctFromSessionLookups(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO CURRENT_USER');
        self::assertInstanceOf(ChangeEventTriggerOwnerStatement::class, $statement);
        $changed = $statement->withNewOwner(new NamedRole('CURRENT_USER'));
        self::assertSame(SessionRole::CurrentUser, $statement->newOwner);
        self::assertEquals(new NamedRole('CURRENT_USER'), $changed->newOwner);
        self::assertSame('ALTER EVENT TRIGGER "audit" OWNER TO "CURRENT_USER"', $changed->toString());
    }

}
