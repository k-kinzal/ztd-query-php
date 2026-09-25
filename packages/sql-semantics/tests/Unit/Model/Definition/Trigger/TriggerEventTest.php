<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\TriggerEvent;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\CreateTriggerStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerEvent::class)]
#[Medium]
final class TriggerEventTest extends TestCase
{
    #[TestWith([TriggerEvent::Insert])]
    #[TestWith([TriggerEvent::Update])]
    #[TestWith([TriggerEvent::Delete])]
    #[TestWith([TriggerEvent::Truncate])]
    public function testEachEventSurvivesBindingAndSerialization(TriggerEvent $event): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('CREATE TRIGGER audit AFTER ' . $event->value . ' ON t EXECUTE FUNCTION f()');
        self::assertInstanceOf(CreateTriggerStatement::class, $statement);
        self::assertSame([$event], $statement->events->events);
        self::assertSame('CREATE TRIGGER "audit" AFTER ' . $event->value . ' ON "public"."t" FOR EACH STATEMENT EXECUTE FUNCTION "f"()', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
