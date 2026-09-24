<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\EventTriggerEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateEventTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(EventTriggerEvent::class)]
#[Medium]
final class EventTriggerEventTest extends TestCase
{
    #[TestWith([EventTriggerEvent::DdlCommandStart])]
    #[TestWith([EventTriggerEvent::DdlCommandEnd])]
    #[TestWith([EventTriggerEvent::SqlDrop])]
    #[TestWith([EventTriggerEvent::TableRewrite])]
    #[TestWith([EventTriggerEvent::Login])]
    public function testEachEventSurvivesBindingAndSerialization(EventTriggerEvent $event): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE EVENT TRIGGER x ON ' . strtoupper($event->value) . ' EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateEventTriggerStatement::class, $statement);
        self::assertSame($event, $statement->event);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
