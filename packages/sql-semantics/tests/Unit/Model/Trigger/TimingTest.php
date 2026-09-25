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
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Timing::class)]
#[Medium]
final class TimingTest extends TestCase
{
    public function testRepresentsEveryTriggerTiming(): void
    {
        self::assertSame(['BEFORE', 'AFTER', 'INSTEAD OF'], array_column(Timing::cases(), 'value'));
    }

    #[TestWith(['CREATE TRIGGER tr BEFORE INSERT ON t BEGIN INSERT INTO t(id) VALUES (new.id); END', Timing::Before])]
    #[TestWith(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO t(id) VALUES (new.id); END', Timing::After])]
    #[TestWith(['CREATE TRIGGER tr INSTEAD OF INSERT ON t BEGIN INSERT INTO t(id) VALUES (new.id); END', Timing::InsteadOf])]
    public function testClassifiesWhenTheTriggerActs(string $sql, Timing $timing): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        self::assertSame($timing, $statement->timing);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
