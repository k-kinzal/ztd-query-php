<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Trigger\TriggerRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerEvent;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\TriggerTiming;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TriggerRule::class)]
#[Medium]
final class TriggerRuleTest extends TestCase
{
    public function testCreateLowersTheDeclarationAndTheProgram(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER IF NOT EXISTS main.tr BEFORE UPDATE OF a, b ON u FOR EACH ROW WHEN new.a > 1 BEGIN SELECT 1; SELECT 2; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        self::assertSame('main', $operation->statement->name->schema?->value);
        self::assertSame('tr', $operation->statement->name->name->value);
        self::assertFalse($operation->statement->temporary);
        self::assertTrue($operation->statement->ifNotExists);
        self::assertSame(TriggerTiming::Before, $operation->statement->timing);
        self::assertSame(TriggerEvent::Update, $operation->statement->event);
        self::assertSame(['a', 'b'], array_map(static fn (Name $column): string => $column->value, $operation->statement->columns));
        self::assertSame('u', $operation->statement->table->name->name->value);
        self::assertTrue($operation->statement->forEachRow);
        self::assertInstanceOf(Binary::class, $operation->statement->when);
        self::assertCount(2, $operation->statement->steps);
        self::assertSame('CREATE TRIGGER IF NOT EXISTS main.tr BEFORE UPDATE OF a, b ON u FOR EACH ROW WHEN new.a > 1 BEGIN SELECT 1; SELECT 2; END', $operation->toString());
    }

    public function testCreateLeavesEveryOptionalPartEmptyWhenNotWritten(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TEMP TRIGGER tr DELETE ON t BEGIN SELECT 1; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        self::assertTrue($operation->statement->temporary);
        self::assertFalse($operation->statement->ifNotExists);
        self::assertNull($operation->statement->name->schema);
        self::assertNull($operation->statement->timing);
        self::assertSame([], $operation->statement->columns);
        self::assertFalse($operation->statement->forEachRow);
        self::assertNull($operation->statement->when);
        self::assertSame('CREATE TEMP TRIGGER tr DELETE ON t BEGIN SELECT 1; END', $operation->toString());
    }

    public function testTimingLowersBeforeAfterInsteadOfAndNone(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $before = $semantics->analyze('CREATE TRIGGER tr BEFORE INSERT ON t BEGIN SELECT 1; END')->statement;
        $after = $semantics->analyze('CREATE TRIGGER tr AFTER INSERT ON t BEGIN SELECT 1; END')->statement;
        $instead = $semantics->analyze('CREATE TRIGGER tr INSTEAD OF INSERT ON v BEGIN SELECT 1; END')->statement;
        $none = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $before);
        self::assertInstanceOf(CreateTrigger::class, $after);
        self::assertInstanceOf(CreateTrigger::class, $instead);
        self::assertInstanceOf(CreateTrigger::class, $none);
        self::assertSame(TriggerTiming::Before, $before->timing);
        self::assertSame(TriggerTiming::After, $after->timing);
        self::assertSame(TriggerTiming::InsteadOf, $instead->timing);
        self::assertNull($none->timing);
        self::assertSame('CREATE TRIGGER tr INSTEAD OF INSERT ON v BEGIN SELECT 1; END', $semantics->analyze('create trigger tr instead of insert on v begin select 1; end')->toString());
    }

    public function testEventLowersDeleteInsertUpdateAndUpdateOf(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $delete = $semantics->analyze('CREATE TRIGGER tr DELETE ON t BEGIN SELECT 1; END')->statement;
        $insert = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;
        $update = $semantics->analyze('CREATE TRIGGER tr UPDATE ON t BEGIN SELECT 1; END')->statement;
        $columns = $semantics->analyze('CREATE TRIGGER tr UPDATE OF a, "b c" ON t BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $delete);
        self::assertInstanceOf(CreateTrigger::class, $insert);
        self::assertInstanceOf(CreateTrigger::class, $update);
        self::assertInstanceOf(CreateTrigger::class, $columns);
        self::assertSame(TriggerEvent::Delete, $delete->event);
        self::assertSame(TriggerEvent::Insert, $insert->event);
        self::assertSame(TriggerEvent::Update, $update->event);
        self::assertSame([], $update->columns);
        self::assertSame(TriggerEvent::Update, $columns->event);
        self::assertSame(['a', 'b c'], array_map(static fn (Name $column): string => $column->value, $columns->columns));
    }

    public function testWhenIsNullWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('CREATE TRIGGER tr INSERT ON t BEGIN SELECT 1; END')->statement;
        $guarded = $semantics->analyze('CREATE TRIGGER tr INSERT ON t WHEN new.a > 1 BEGIN SELECT 1; END')->statement;

        self::assertInstanceOf(CreateTrigger::class, $bare);
        self::assertInstanceOf(CreateTrigger::class, $guarded);
        self::assertNull($bare->when);
        self::assertInstanceOf(Binary::class, $guarded->when);
    }

    public function testProgramLowersEveryStatementInWrittenOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER tr INSERT ON t BEGIN INSERT INTO u VALUES (1); UPDATE u SET a = 1; DELETE FROM u; SELECT 1; INSERT INTO u SELECT 1; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        self::assertSame([InsertRows::class, Update::class, Delete::class, Select::class, InsertSelect::class], array_map(static fn (object $step): string => $step::class, $operation->statement->steps));
        self::assertSame('CREATE TRIGGER tr INSERT ON t BEGIN INSERT INTO u VALUES (1); UPDATE u SET a = 1; DELETE FROM u; SELECT 1; INSERT INTO u SELECT 1; END', $operation->toString());
    }

    public function testStepLowersAnUpdateWithEveryClause(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER tr INSERT ON t BEGIN UPDATE OR IGNORE u SET a = 1, b = 2 FROM w WHERE u.b = w.b; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        $update = $operation->statement->steps[0];
        self::assertInstanceOf(Update::class, $update);
        self::assertSame(ConflictResolution::Ignore, $update->resolution);
        self::assertSame('u', $update->target->name->name->value);
        self::assertCount(2, $update->assignments);
        self::assertInstanceOf(TableInput::class, $update->from);
        self::assertInstanceOf(Binary::class, $update->where);
        self::assertSame([], $update->returning);
        self::assertNull($update->with);
        self::assertSame('CREATE TRIGGER tr INSERT ON t BEGIN UPDATE OR IGNORE u SET a = 1, b = 2 FROM w WHERE u.b = w.b; END', $operation->toString());
    }

    public function testStepLowersAnInsertWithItsUpsertAndADeleteWithItsPredicate(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER tr INSERT ON t BEGIN INSERT OR REPLACE INTO u (a) VALUES (1) ON CONFLICT DO NOTHING; DELETE FROM u WHERE a = 1; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        $insert = $operation->statement->steps[0];
        $delete = $operation->statement->steps[1];
        self::assertInstanceOf(InsertRows::class, $insert);
        self::assertSame(ConflictResolution::Replace, $insert->into->resolution);
        self::assertFalse($insert->into->replace);
        self::assertCount(1, $insert->into->columns);
        self::assertCount(1, $insert->upserts);
        self::assertInstanceOf(Delete::class, $delete);
        self::assertInstanceOf(Binary::class, $delete->where);
        self::assertSame('CREATE TRIGGER tr INSERT ON t BEGIN INSERT OR REPLACE INTO u (a) VALUES (1) ON CONFLICT DO NOTHING; DELETE FROM u WHERE a = 1; END', $operation->toString());
    }

    public function testTargetLowersTheWrittenTableWithItsIndexChoiceAndReportsWhatSqliteRefuses(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('CREATE TRIGGER tr INSERT ON t BEGIN UPDATE u INDEXED BY i SET a = 1; DELETE FROM u NOT INDEXED; DELETE FROM main.u; END');

        self::assertInstanceOf(CreateTrigger::class, $operation->statement);
        $indexed = $operation->statement->steps[0];
        $refused = $operation->statement->steps[1];
        $qualified = $operation->statement->steps[2];
        self::assertInstanceOf(Update::class, $indexed);
        self::assertInstanceOf(Delete::class, $refused);
        self::assertInstanceOf(Delete::class, $qualified);
        self::assertSame('i', $indexed->target->index?->index?->value);
        self::assertNotNull($refused->target->index);
        self::assertNull($refused->target->index->index);
        self::assertNull($qualified->target->index);
        self::assertSame('main', $qualified->target->name->schema?->value);
        self::assertSame([MisuseRule::IndexedTriggerTarget, MisuseRule::IndexedTriggerTarget, MisuseRule::QualifiedTriggerTarget], array_map(static function (object $diagnostic): MisuseRule {
            self::assertInstanceOf(Misuse::class, $diagnostic);

            return $diagnostic->rule;
        }, $operation->facts->diagnostics));
        self::assertSame('CREATE TRIGGER tr INSERT ON t BEGIN UPDATE u INDEXED BY i SET a = 1; DELETE FROM u NOT INDEXED; DELETE FROM main.u; END', $operation->toString());
    }
}
