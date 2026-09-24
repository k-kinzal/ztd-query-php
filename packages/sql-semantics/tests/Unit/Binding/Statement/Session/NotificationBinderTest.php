<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Session\NotificationBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ConstraintTiming;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NotificationBinder::class)]
#[Medium]
final class NotificationBinderTest extends TestCase
{
    public function testBindReadsChannelIdentitiesForListenAndUnlisten(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $listen = $binder->bind('LISTEN ch');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\ListenStatement::class, $listen);
        self::assertSame('ch', $listen->channel);
        self::assertSame('LISTEN "ch"', $listen->toString());
        $unlisten = $binder->bind('UNLISTEN ch');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\UnlistenStatement::class, $unlisten);
        self::assertSame('ch', $unlisten->channel);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\UnlistenAllStatement::class, $binder->bind('UNLISTEN *'));
    }

    public function testBindKeepsAnOptionalNotificationPayload(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $payload = $binder->bind("NOTIFY ch, 'payload'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\NotifyStatement::class, $payload);
        self::assertSame('ch', $payload->channel);
        self::assertSame("'payload'", $payload->payload?->text);
        self::assertSame("NOTIFY \"ch\", 'payload'", $payload->toString());
        $bare = $binder->bind('NOTIFY ch');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\NotifyStatement::class, $bare);
        self::assertNull($bare->payload);
    }

    public function testBindClassifiesDiscardResourcesAndConstraintTiming(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $temp = $binder->bind('DISCARD TEMP');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\DiscardStatement::class, $temp);
        self::assertSame(\SqlSemantics\Model\Configuration\DiscardResource::TemporaryTables, $temp->resource);
        self::assertSame('DISCARD TEMPORARY', $temp->toString());
        $sequences = $binder->bind('DISCARD SEQUENCES');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\DiscardStatement::class, $sequences);
        self::assertSame(\SqlSemantics\Model\Configuration\DiscardResource::Sequences, $sequences->resource);
        $constraints = $binder->bind('SET CONSTRAINTS ALL DEFERRED');
        self::assertInstanceOf(SetAllConstraintsStatement::class, $constraints);
        self::assertSame(ConstraintTiming::Deferred, $constraints->timing);
    }

    #[TestWith(['listen ch', 'LISTEN "ch"'])]
    #[TestWith(['discard temp', 'DISCARD TEMPORARY'])]
    #[TestWith(['discard all', 'DISCARD ALL'])]
    public function testBindReadsLowercaseCommands(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    #[TestWith(['SET CONSTRAINTS ALL DEFERRED', ConstraintTiming::Deferred])]
    #[TestWith(['set constraints all immediate', ConstraintTiming::Immediate])]
    public function testBindReadsTheTimingOfAllConstraints(string $sql, ConstraintTiming $timing): void
    {
        $node = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('ConstraintsSetStmt')[0];
        $statement = NotificationBinder::bind(new Origin('s0', $node, Dialect::PostgreSql), $node, new Scope(new Identifiers(Dialect::PostgreSql)));
        self::assertInstanceOf(SetAllConstraintsStatement::class, $statement);
        self::assertSame($timing, $statement->timing);
    }

    #[TestWith(['SET CONSTRAINTS c DEFERRED'])]
    #[TestWith(['SET TIME ZONE LOCAL'])]
    public function testBindReturnsNullForOtherStatements(string $sql): void
    {
        $node = (new DialectParser(Dialect::PostgreSql))->parse($sql)->find('stmt')[0];
        self::assertNull(NotificationBinder::bind(new Origin('s0', $node, Dialect::PostgreSql), $node, new Scope(new Identifiers(Dialect::PostgreSql))));
    }
}
