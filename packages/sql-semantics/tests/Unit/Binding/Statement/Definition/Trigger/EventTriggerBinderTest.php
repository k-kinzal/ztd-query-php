<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\ChangeEventTriggerOwnerStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\DropEventTriggersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\RenameEventTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Trigger\EventTriggerBinder::class)]
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

}
