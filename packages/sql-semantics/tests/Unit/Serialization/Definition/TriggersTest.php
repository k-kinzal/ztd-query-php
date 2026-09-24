<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement;
use SqlSemantics\Model\Trigger\UpdatedColumns;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Triggers;

#[CoversClass(Triggers::class)]
#[Medium]
final class TriggersTest extends TestCase
{
    public function testWriteSqliteSerializesEveryStepFromTheSemanticStructure(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)', 'CREATE TABLE log(id INTEGER)'));
        $statement = $binder->bind("CREATE TEMP TRIGGER IF NOT EXISTS main.tr BEFORE UPDATE OF id, x ON t FOR EACH ROW WHEN new.id > 0 BEGIN UPDATE t SET x = new.id WHERE id = old.id; INSERT INTO log (id) VALUES (new.id); DELETE FROM log WHERE id = old.id; SELECT RAISE(ABORT, 'no'); END");
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        $expected = "CREATE TEMP TRIGGER IF NOT EXISTS \"main\".\"tr\" BEFORE UPDATE OF \"id\", \"x\" ON \"main\".\"t\" FOR EACH ROW WHEN (\"new\".\"id\" > 0) BEGIN UPDATE \"t\" SET \"x\" = \"new\".\"id\" WHERE (\"id\" = \"old\".\"id\"); INSERT INTO \"log\"(\"id\") VALUES (\"new\".\"id\"); DELETE FROM \"log\" WHERE (\"id\" = \"old\".\"id\"); SELECT RAISE(ABORT, 'no'); END";
        self::assertSame($expected, Triggers::writeSqlite($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $rebound);
        self::assertInstanceOf(UpdatedColumns::class, $rebound->event);
        self::assertSame(['id', 'x'], $rebound->event->columns);
        self::assertCount(4, $rebound->body->steps);
        self::assertTrue($rebound->temporary);
        self::assertNotNull($rebound->when);
        self::assertSame($expected, $rebound->toString());
    }

    #[TestWith(['CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT RAISE(IGNORE); END', 'CREATE TRIGGER "tr" AFTER INSERT ON "main"."t" FOR EACH ROW BEGIN SELECT RAISE(IGNORE); END'])]
    #[TestWith(["CREATE TRIGGER tr INSTEAD OF DELETE ON t BEGIN SELECT RAISE(FAIL, 'x'); END", "CREATE TRIGGER \"tr\" INSTEAD OF DELETE ON \"main\".\"t\" FOR EACH ROW BEGIN SELECT RAISE(FAIL, 'x'); END"])]
    public function testWriteSqliteWritesTimingAndOperationWithoutColumnsWhenNoneAreNamed(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(CreateSqliteTriggerStatement::class, $rebound);
        self::assertSame($statement->timing, $rebound->timing);
        self::assertSame($statement->event->operation(), $rebound->event->operation());
    }
}
