<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Trigger\EventTriggerBinder;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\Trigger\TriggerFiring;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\AlterEventTriggerFiringStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\ChangeEventTriggerOwnerStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\DropEventTriggersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\RenameEventTriggerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventTriggerBinder::class)]
#[Medium]
final class EventTriggerBinderTest extends TestCase
{
    public function testBindKeepsKeywordSpellingInsideQuotedNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER "ENABLE" RENAME TO "CURRENT_USER"');
        self::assertInstanceOf(RenameEventTriggerStatement::class, $statement);
        self::assertSame('ENABLE', $statement->name);
        self::assertSame('CURRENT_USER', $statement->newName);
    }

    public function testBindRemovalHasItsOwnSelectionAndDependencyPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER IF EXISTS audit, "CASCADE" RESTRICT');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        self::assertSame(['audit', 'CASCADE'], $statement->names);
        self::assertTrue($statement->ifExists);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Restrict, $statement->behavior);
    }

    public function testBindOwnerTransfersRetainASessionReference(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO SESSION_USER');
        self::assertInstanceOf(ChangeEventTriggerOwnerStatement::class, $statement);
        self::assertSame(SessionRole::SessionUser, $statement->newOwner);
    }

    public function testBindDoesNotAcceptThePublicPrivilegeGroupAsAnOwner(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER EVENT TRIGGER audit OWNER TO PUBLIC');
    }

    #[TestWith(['ALTER EVENT TRIGGER e ENABLE ALWAYS', TriggerFiring::Always])]
    #[TestWith(['alter event trigger e enable replica', TriggerFiring::Replica])]
    #[TestWith(['ALTER EVENT TRIGGER e DISABLE', TriggerFiring::Disabled])]
    public function testBindReadsTheFiringPolicy(string $sql, TriggerFiring $firing): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), ''));
        $source = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('AlterEventTrigStmt')[0];
        $statement = EventTriggerBinder::bind(new Origin('s0', $source, Dialect::PostgreSql), $source, $context);
        self::assertInstanceOf(AlterEventTriggerFiringStatement::class, $statement);
        self::assertSame(['e', $firing], [$statement->name, $statement->firing]);
    }

    #[TestWith(['DROP TABLE trigger', 'DropStmt'])]
    #[TestWith(['CREATE EVENT TRIGGER e ON ddl_command_start EXECUTE FUNCTION f()', 'CreateEventTrigStmt'])]
    public function testBindReturnsNullForOtherOperations(string $sql, string $rule): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), ''));
        $source = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find($rule)[0];
        self::assertNull(EventTriggerBinder::bind(new Origin('s0', $source, Dialect::PostgreSql), $source, $context));
    }

    public function testBindRemovalWithoutIfExistsOrBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('drop event trigger e cascade');
        self::assertInstanceOf(DropEventTriggersStatement::class, $statement);
        self::assertSame('DROP EVENT TRIGGER "e" CASCADE', $statement->toString());
        self::assertFalse($statement->ifExists);
    }
}
