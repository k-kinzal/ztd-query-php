<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Trigger\UpdatedColumns;
use SqlSemantics\Model\Trigger\WriteEvent;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpdatedColumns::class)]
#[Medium]
final class UpdatedColumnsTest extends TestCase
{
    public function testRetainsTheSelectedColumnsInWrittenOrder(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $statement = (new Binder($schema))->bind('CREATE TRIGGER tr AFTER UPDATE OF x, id ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END');
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $event = $statement->event;
        self::assertInstanceOf(UpdatedColumns::class, $event);
        self::assertSame(['x', 'id'], $event->columns);
        self::assertSame(WriteEvent::Update, $event->operation());
        self::assertSame('CREATE TRIGGER "tr" AFTER UPDATE OF "x", "id" ON "main"."t" FOR EACH ROW BEGIN UPDATE "t" SET "x" = "new"."id" WHERE ("id" = "old"."id"); END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testOperationIsAlwaysAnUpdate(): void
    {
        $event = new UpdatedColumns(['x']);
        self::assertSame(WriteEvent::Update, $event->operation());
        self::assertSame(['x'], $event->columns);
    }

    public function testRejectsAnEmptyColumnList(): void
    {
        $this->expectException(InvalidStructure::class);
        new UpdatedColumns([]);
    }
}
