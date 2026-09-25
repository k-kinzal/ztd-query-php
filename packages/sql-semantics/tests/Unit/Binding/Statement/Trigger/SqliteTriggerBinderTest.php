<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Trigger\SqliteTriggerBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SqliteTriggerBinder::class)]
#[Medium]
final class SqliteTriggerBinderTest extends TestCase
{
    public function testBindRetainsNameSubjectTimingEventConditionAndFlags(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('CREATE TEMP TRIGGER IF NOT EXISTS tr AFTER UPDATE OF a ON t WHEN NEW.a > OLD.a BEGIN INSERT INTO u VALUES (NEW.a); END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $statement);
        self::assertSame(['tr'], $statement->name->parts);
        self::assertSame(['main', 't'], $statement->subject->name->parts);
        self::assertSame(\SqlSemantics\Model\Trigger\Timing::After, $statement->timing);
        self::assertInstanceOf(\SqlSemantics\Model\Trigger\UpdatedColumns::class, $statement->event);
        self::assertSame(['a'], $statement->event->columns);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Operator\BinaryExpression::class, $statement->when);
        self::assertTrue($statement->temporary);
        self::assertTrue($statement->ifNotExists);
        self::assertSame([], $statement->diagnostics);
        self::assertSame('CREATE TEMP TRIGGER IF NOT EXISTS "tr" AFTER UPDATE OF "a" ON "main"."t" FOR EACH ROW WHEN ("new"."a" > "old"."a") BEGIN INSERT INTO "u" VALUES ("new"."a"); END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testEventReadsPlainWriteEventsAndQualifiedTriggerNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)')))->bind('CREATE TRIGGER main.tr DELETE ON t BEGIN DELETE FROM u WHERE a = OLD.a; END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $statement);
        self::assertSame(['main', 'tr'], $statement->name->parts);
        self::assertSame(\SqlSemantics\Model\Trigger\WriteEvent::Delete, $statement->event);
        self::assertSame(\SqlSemantics\Model\Trigger\Timing::Before, $statement->timing);
        self::assertNull($statement->when);
        self::assertFalse($statement->temporary);
    }

    public function testScopeExposesOnlyTheRowImagesOfTheTriggeringOperation(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(a INT)', 'CREATE TABLE u(a INT)'));
        $insert = $binder->bind('CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO u VALUES (NEW.a); END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $insert);
        $row = $insert->body->steps[0];
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $row);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\TriggerColumn::class, $row->rows[0][0]);
        self::assertSame(\SqlSemantics\Model\Trigger\RowVersion::New, $row->rows[0][0]->version);
        $missingOld = $binder->bind('CREATE TRIGGER tr AFTER INSERT ON t BEGIN INSERT INTO u VALUES (OLD.a); END', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $missingOld);
        self::assertSame('unknown-column', $missingOld->diagnostics[0]->reason);
        $delete = $binder->bind('CREATE TRIGGER tr AFTER DELETE ON t BEGIN INSERT INTO u VALUES (OLD.a); END');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\CreateSqliteTriggerStatement::class, $delete);
        self::assertSame([], $delete->diagnostics);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsLowercaseTriggers')]
    public function testBindReadsLowercaseTriggers(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsLowercaseTriggers(): iterable
    {
        return [
            'create trigger tr before insert on t begin select 1; end (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT, b INT)'], 'create trigger tr before insert on t begin select 1; end', 'CREATE TRIGGER "tr" BEFORE INSERT ON "main"."t" FOR EACH ROW BEGIN SELECT 1; END'],
            'create temp trigger if not exists tr after delete on t for each row when old.a > 1 begin select old.a; end (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT, b INT)'], 'create temp trigger if not exists tr after delete on t for each row when old.a > 1 begin select old.a; end', 'CREATE TEMP TRIGGER IF NOT EXISTS "tr" AFTER DELETE ON "main"."t" FOR EACH ROW WHEN ("old"."a" > 1) BEGIN SELECT "old"."a" AS "a"; END'],
            'create trigger tr instead of update of a, b on t begin select new.a; end (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT, b INT)'], 'create trigger tr instead of update of a, b on t begin select new.a; end', 'CREATE TRIGGER "tr" INSTEAD OF UPDATE OF "a", "b" ON "main"."t" FOR EACH ROW BEGIN SELECT "new"."a" AS "a"; END'],
            'create trigger main.tr update on t begin select 1; end (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT, b INT)'], 'create trigger main.tr update on t begin select 1; end', 'CREATE TRIGGER "main"."tr" BEFORE UPDATE ON "main"."t" FOR EACH ROW BEGIN SELECT 1; END'],
            'CREATE TRIGGER tr AFTER UPDATE ON t BEGIN SELECT 1; END (Sqlite)' => [Dialect::Sqlite, null, ['CREATE TABLE t (a INT, b INT)'], 'CREATE TRIGGER tr AFTER UPDATE ON t BEGIN SELECT 1; END', 'CREATE TRIGGER "tr" AFTER UPDATE ON "main"."t" FOR EACH ROW BEGIN SELECT 1; END'],
        ];
    }
}
