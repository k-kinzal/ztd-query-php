<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Mutation\MutationRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\RowExpression;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Assignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Delete;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertDefaults;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertSelect;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\RowAssignment;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(MutationRule::class)]
#[Medium]
final class MutationRuleTest extends TestCase
{
    public function testCommandLowersEachDataChangeForm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);

        self::assertInstanceOf(Delete::class, $semantics->analyze('DELETE FROM t')->statement);
        self::assertInstanceOf(Update::class, $semantics->analyze('UPDATE t SET a = 1')->statement);
        self::assertInstanceOf(InsertRows::class, $semantics->analyze('INSERT INTO t VALUES (1)')->statement);
        self::assertInstanceOf(InsertSelect::class, $semantics->analyze('INSERT INTO t SELECT 1')->statement);
        self::assertInstanceOf(InsertDefaults::class, $semantics->analyze('INSERT INTO t DEFAULT VALUES')->statement);
    }

    public function testDeleteLowersTheTargetThePredicateTheReturningAndTheWithClause(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1) DELETE FROM t INDEXED BY i WHERE a IN c RETURNING *');

        self::assertInstanceOf(Delete::class, $operation->statement);
        self::assertNotNull($operation->statement->with);
        self::assertSame('t', $operation->statement->target->name->name->value);
        self::assertSame('i', $operation->statement->target->index?->index?->value);
        self::assertNotNull($operation->statement->where);
        self::assertCount(1, $operation->statement->returning);
        self::assertInstanceOf(Star::class, $operation->statement->returning[0]);
        self::assertSame('WITH c AS (SELECT 1) DELETE FROM t INDEXED BY i WHERE a IN c RETURNING *', $operation->toString());
    }

    public function testUpdateLowersEveryClauseInGrammarOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('WITH c AS (SELECT 1) UPDATE OR REPLACE main.t AS x INDEXED BY i SET a = 1 FROM u WHERE x.id = u.id RETURNING x.*');

        self::assertInstanceOf(Update::class, $operation->statement);
        self::assertNotNull($operation->statement->with);
        self::assertSame(ConflictResolution::Replace, $operation->statement->resolution);
        self::assertSame('main', $operation->statement->target->name->schema?->value);
        self::assertSame('t', $operation->statement->target->name->name->value);
        self::assertSame('x', $operation->statement->target->alias?->value);
        self::assertSame('i', $operation->statement->target->index?->index?->value);
        self::assertCount(1, $operation->statement->assignments);
        self::assertInstanceOf(TableInput::class, $operation->statement->from);
        self::assertInstanceOf(Binary::class, $operation->statement->where);
        self::assertCount(1, $operation->statement->returning);
        self::assertInstanceOf(TableStar::class, $operation->statement->returning[0]);
        self::assertSame('WITH c AS (SELECT 1) UPDATE OR REPLACE main.t AS x INDEXED BY i SET a = 1 FROM u WHERE x.id = u.id RETURNING x.*', $operation->toString());
    }

    public function testUpdateLeavesEveryOptionalClauseEmptyWhenNotWritten(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('UPDATE t SET a = 1')->statement;

        self::assertInstanceOf(Update::class, $statement);
        self::assertNull($statement->with);
        self::assertNull($statement->resolution);
        self::assertNull($statement->target->alias);
        self::assertNull($statement->target->index);
        self::assertNull($statement->from);
        self::assertNull($statement->where);
        self::assertSame([], $statement->returning);
    }

    public function testIntoLowersAnInsertWithItsOrClauseAndAReplace(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $insert = $semantics->analyze('INSERT OR IGNORE INTO t (a, b) VALUES (1, 2)');
        $replace = $semantics->analyze('REPLACE INTO t VALUES (1)');

        self::assertInstanceOf(InsertRows::class, $insert->statement);
        self::assertInstanceOf(InsertRows::class, $replace->statement);
        self::assertFalse($insert->statement->into->replace);
        self::assertSame(ConflictResolution::Ignore, $insert->statement->into->resolution);
        self::assertSame(['a', 'b'], array_map(static fn (Name $column): string => $column->value, $insert->statement->into->columns));
        self::assertNull($insert->statement->into->with);
        self::assertTrue($replace->statement->into->replace);
        self::assertNull($replace->statement->into->resolution);
        self::assertSame([], $replace->statement->into->columns);
        self::assertSame('INSERT OR IGNORE INTO t (a, b) VALUES (1, 2)', $insert->toString());
        self::assertSame('REPLACE INTO t VALUES (1)', $replace->toString());
    }

    public function testInsertLowersWrittenRowsAndEveryOtherSourceAsAQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $rows = $semantics->analyze('INSERT INTO t VALUES (1), (2)')->statement;
        $select = $semantics->analyze('INSERT INTO t SELECT * FROM u')->statement;
        $compound = $semantics->analyze('INSERT INTO t VALUES (1) UNION SELECT 2')->statement;
        $with = $semantics->analyze('INSERT INTO t WITH c AS (SELECT 1) SELECT * FROM c')->statement;

        self::assertInstanceOf(InsertRows::class, $rows);
        self::assertCount(2, $rows->rows->rows);
        self::assertInstanceOf(InsertSelect::class, $select);
        self::assertInstanceOf(Select::class, $select->query);
        self::assertInstanceOf(InsertSelect::class, $compound);
        self::assertInstanceOf(Compound::class, $compound->query);
        self::assertInstanceOf(InsertSelect::class, $with);
        self::assertInstanceOf(WithQuery::class, $with->query);
    }

    public function testDefaultsLowersAnInsertOfDefaultValuesWithItsReturning(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $returning = $semantics->analyze('INSERT INTO main.t AS x DEFAULT VALUES RETURNING *');
        $bare = $semantics->analyze('INSERT OR FAIL INTO t DEFAULT VALUES');

        self::assertInstanceOf(InsertDefaults::class, $returning->statement);
        self::assertInstanceOf(InsertDefaults::class, $bare->statement);
        self::assertSame('main', $returning->statement->into->target->name->schema?->value);
        self::assertSame('x', $returning->statement->into->target->alias?->value);
        self::assertCount(1, $returning->statement->returning);
        self::assertSame([], $bare->statement->returning);
        self::assertSame(ConflictResolution::Fail, $bare->statement->into->resolution);
        self::assertSame('INSERT INTO main.t AS x DEFAULT VALUES RETURNING *', $returning->toString());
        self::assertSame('INSERT OR FAIL INTO t DEFAULT VALUES', $bare->toString());
    }

    public function testTargetLowersEveryFormOfTheWrittenTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('DELETE FROM t')->statement;
        $qualified = $semantics->analyze('DELETE FROM main.t')->statement;
        $aliased = $semantics->analyze('DELETE FROM t AS x')->statement;
        $both = $semantics->analyze('DELETE FROM main.t AS x')->statement;

        self::assertInstanceOf(Delete::class, $plain);
        self::assertInstanceOf(Delete::class, $qualified);
        self::assertInstanceOf(Delete::class, $aliased);
        self::assertInstanceOf(Delete::class, $both);
        self::assertNull($plain->target->name->schema);
        self::assertNull($plain->target->alias);
        self::assertSame('main', $qualified->target->name->schema?->value);
        self::assertNull($qualified->target->alias);
        self::assertNull($aliased->target->name->schema);
        self::assertSame('x', $aliased->target->alias?->value);
        self::assertSame('main', $both->target->name->schema?->value);
        self::assertSame('x', $both->target->alias?->value);
        self::assertSame('DELETE FROM main.t AS x', $semantics->analyze('DELETE FROM main.t AS x')->toString());
    }

    public function testAssignmentsKeepsTheWrittenOrderAndTheListForm(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('UPDATE t SET a = 1, (b, c) = (2, 3), d = 4');

        self::assertInstanceOf(Update::class, $operation->statement);
        $assignments = $operation->statement->assignments;
        self::assertCount(3, $assignments);
        self::assertInstanceOf(Assignment::class, $assignments[0]);
        self::assertSame('a', $assignments[0]->column->value);
        self::assertInstanceOf(RowAssignment::class, $assignments[1]);
        self::assertSame(['b', 'c'], array_map(static fn (Name $column): string => $column->value, $assignments[1]->columns));
        self::assertInstanceOf(RowExpression::class, $assignments[1]->value);
        self::assertInstanceOf(Assignment::class, $assignments[2]);
        self::assertSame('d', $assignments[2]->column->value);
        self::assertSame('UPDATE t SET a = 1, (b, c) = (2, 3), d = 4', $operation->toString());
    }

    public function testAssignmentsStartWithAListForm(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('UPDATE t SET (a, b) = (SELECT 1, 2), c = 3');

        self::assertInstanceOf(Update::class, $operation->statement);
        self::assertInstanceOf(RowAssignment::class, $operation->statement->assignments[0]);
        self::assertInstanceOf(Assignment::class, $operation->statement->assignments[1]);
        self::assertSame('UPDATE t SET (a, b) = (SELECT 1, 2), c = 3', $operation->toString());
    }

    public function testWhereReturningLowersEachOfTheFourTails(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $none = $semantics->analyze('DELETE FROM t')->statement;
        $where = $semantics->analyze('DELETE FROM t WHERE a')->statement;
        $returning = $semantics->analyze('DELETE FROM t RETURNING a')->statement;
        $both = $semantics->analyze('DELETE FROM t WHERE a RETURNING a, b')->statement;

        self::assertInstanceOf(Delete::class, $none);
        self::assertInstanceOf(Delete::class, $where);
        self::assertInstanceOf(Delete::class, $returning);
        self::assertInstanceOf(Delete::class, $both);
        self::assertNull($none->where);
        self::assertSame([], $none->returning);
        self::assertNotNull($where->where);
        self::assertSame([], $where->returning);
        self::assertNull($returning->where);
        self::assertCount(1, $returning->returning);
        self::assertNotNull($both->where);
        self::assertCount(2, $both->returning);
        self::assertInstanceOf(ResultColumn::class, $both->returning[0]);
    }

    public function testReturningIsEmptyWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('INSERT INTO t DEFAULT VALUES')->statement;
        $listed = $semantics->analyze('INSERT INTO t DEFAULT VALUES RETURNING a, b AS c')->statement;

        self::assertInstanceOf(InsertDefaults::class, $bare);
        self::assertInstanceOf(InsertDefaults::class, $listed);
        self::assertSame([], $bare->returning);
        self::assertCount(2, $listed->returning);
        self::assertInstanceOf(ResultColumn::class, $listed->returning[1]);
        self::assertSame('c', $listed->returning[1]->alias?->value);
    }
}
