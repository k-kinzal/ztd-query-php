<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Trigger\Event;
use SqlSemantics\Model\Trigger\UpdatedColumns;
use SqlSemantics\Model\Trigger\WriteEvent;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Event::class)]
#[Medium]
final class EventTest extends TestCase
{
    /**
     * @param class-string<Event> $class
     */
    #[TestWith(['CREATE TRIGGER tr BEFORE INSERT ON t BEGIN INSERT INTO t(id) VALUES (new.id); END', WriteEvent::class, WriteEvent::Insert])]
    #[TestWith(['CREATE TRIGGER tr AFTER UPDATE ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END', WriteEvent::class, WriteEvent::Update])]
    #[TestWith(['CREATE TRIGGER tr AFTER UPDATE OF id, x ON t BEGIN UPDATE t SET x=new.id WHERE id=old.id; END', UpdatedColumns::class, WriteEvent::Update])]
    #[TestWith(['CREATE TRIGGER tr INSTEAD OF DELETE ON t BEGIN DELETE FROM t WHERE id=old.id; END', WriteEvent::class, WriteEvent::Delete])]
    public function testOperationIdentifiesTheWriteOfEveryEvent(string $sql, string $class, WriteEvent $operation): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        self::assertInstanceOf($class, $statement->event);
        self::assertSame($operation, $statement->event->operation());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
