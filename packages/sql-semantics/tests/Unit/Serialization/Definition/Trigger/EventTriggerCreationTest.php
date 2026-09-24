<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Trigger\EventTriggerCreation;

#[CoversClass(EventTriggerCreation::class)]
#[Medium]
final class EventTriggerCreationTest extends TestCase
{
    #[TestWith(['CREATE EVENT TRIGGER "x" ON "login" EXECUTE FUNCTION "f"()'])]
    #[TestWith(['CREATE EVENT TRIGGER "x" ON "sql_drop" WHEN TAG IN(\'DROP TABLE\', \'DROP VIEW\') EXECUTE FUNCTION "s"."f"()'])]
    public function testWriteIsAFixedPoint(string $sql): void
    {
        self::assertSame($sql, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(EventTriggerCreation::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP EVENT TRIGGER x')));
    }
}
