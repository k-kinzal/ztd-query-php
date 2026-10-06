<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Leaf\ConflictRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Raise;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RaiseAction;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnPrimaryKey;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnUnique;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Statement\Trigger\CreateTrigger;

#[CoversClass(ConflictRule::class)]
#[Medium]
final class ConflictRuleTest extends TestCase
{
    public function testResolutionLowersEachOfTheFiveAlgorithms(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $ignore = $semantics->analyze('INSERT OR IGNORE INTO t VALUES (1)')->statement;
        $replace = $semantics->analyze('INSERT OR REPLACE INTO t VALUES (1)')->statement;
        $rollback = $semantics->analyze('INSERT OR ROLLBACK INTO t VALUES (1)')->statement;
        $abort = $semantics->analyze('INSERT OR ABORT INTO t VALUES (1)')->statement;
        $fail = $semantics->analyze('INSERT OR FAIL INTO t VALUES (1)')->statement;

        self::assertInstanceOf(InsertRows::class, $ignore);
        self::assertInstanceOf(InsertRows::class, $replace);
        self::assertInstanceOf(InsertRows::class, $rollback);
        self::assertInstanceOf(InsertRows::class, $abort);
        self::assertInstanceOf(InsertRows::class, $fail);
        self::assertSame(ConflictResolution::Ignore, $ignore->into->resolution);
        self::assertSame(ConflictResolution::Replace, $replace->into->resolution);
        self::assertSame(ConflictResolution::Rollback, $rollback->into->resolution);
        self::assertSame(ConflictResolution::Abort, $abort->into->resolution);
        self::assertSame(ConflictResolution::Fail, $fail->into->resolution);
    }

    public function testResolutionRendersTheAlgorithmAsWritten(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertSame('INSERT OR IGNORE INTO t VALUES (1)', $semantics->analyze('insert or ignore into t values (1)')->toString());
        self::assertSame('UPDATE OR REPLACE t SET a = 1', $semantics->analyze('update or replace t set a = 1')->toString());
    }

    public function testRaisedLowersTheAlgorithmsRaiseCanName(): void
    {
        $trigger = (new Semantics(Dialect::Sqlite))->analyze("CREATE TRIGGER tr INSERT ON t BEGIN SELECT RAISE(FAIL, 'x'), RAISE(ROLLBACK, 'y'), RAISE(ABORT, 'z'); END")->statement;

        self::assertInstanceOf(CreateTrigger::class, $trigger);
        $select = $trigger->steps[0];
        self::assertInstanceOf(Select::class, $select);
        self::assertSame([RaiseAction::Fail, RaiseAction::Rollback, RaiseAction::Abort], array_map(static function (object $column): RaiseAction {
            self::assertInstanceOf(ResultColumn::class, $column);
            self::assertInstanceOf(Raise::class, $column->expression);

            return $column->expression->action;
        }, $select->columns));
    }

    public function testOnConflictLowersTheAlgorithmOfAConstraintAndNullWithoutTheClause(): void
    {
        $table = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a UNIQUE ON CONFLICT IGNORE, b UNIQUE, c PRIMARY KEY ON CONFLICT ABORT)')->statement;

        self::assertInstanceOf(CreateTable::class, $table);
        $ignore = $table->columns[0]->constraints[0];
        $plain = $table->columns[1]->constraints[0];
        $abort = $table->columns[2]->constraints[0];
        self::assertInstanceOf(ColumnUnique::class, $ignore);
        self::assertInstanceOf(ColumnUnique::class, $plain);
        self::assertInstanceOf(ColumnPrimaryKey::class, $abort);
        self::assertSame(ConflictResolution::Ignore, $ignore->conflict);
        self::assertNull($plain->conflict);
        self::assertSame(ConflictResolution::Abort, $abort->conflict);
    }

    public function testOrConflictLowersTheAlgorithmAfterOrAndNullWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('UPDATE t SET a = 1')->statement;
        $fail = $semantics->analyze('UPDATE OR FAIL t SET a = 1')->statement;
        $insert = $semantics->analyze('INSERT INTO t VALUES (1)')->statement;

        self::assertInstanceOf(Update::class, $plain);
        self::assertInstanceOf(Update::class, $fail);
        self::assertInstanceOf(InsertRows::class, $insert);
        self::assertNull($plain->resolution);
        self::assertSame(ConflictResolution::Fail, $fail->resolution);
        self::assertNull($insert->into->resolution);
    }
}
