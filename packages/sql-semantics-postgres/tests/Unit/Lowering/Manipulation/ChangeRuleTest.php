<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\ChangeRule::class)]
#[Medium]
final class ChangeRuleTest extends TestCase
{
    public function testUpdateLowersEveryClause(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('WITH w AS (SELECT 1) UPDATE t SET a = 1 FROM u, w WHERE u.c RETURNING *');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList::class, $statement->from);
    }

    public function testDeleteLowersEveryClause(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t USING u WHERE CURRENT OF c RETURNING 1');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $query->statement);
    }

    public function testUsingIsNullWithoutClause(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        self::assertNull($statement->using);
    }

    public function testTargetLowersAnAliasWithoutAs(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE ONLY t x SET a = 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        self::assertSame([true, 'x'], [$statement->target->table->only, $statement->target->alias()?->value]);
    }

    public function testAssignmentsLowersBothForms(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE t SET a = 1, (b, c) = (SELECT 1, 2)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment::class, $statement->assignments[1]);
    }

    public function testColumnsLowersTheTargetList(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE t SET (a, b, c) = ROW(1, 2, 3)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        $assignment = $statement->assignments[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\RowAssignment::class, $assignment);
        self::assertCount(3, $assignment->columns);
    }

    public function testColumnLowersTheSteps(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE t SET a[1] = 1');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        $assignment = $statement->assignments[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\Assignment::class, $assignment);
        self::assertCount(1, $assignment->column->steps);
    }

    public function testWhereLowersTheCursor(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('UPDATE t SET a = 1 WHERE CURRENT OF c');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Update::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf::class, $statement->where);
    }

    public function testReturningLowersTheTargets(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t RETURNING a, b AS c');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        self::assertCount(2, $statement->returning);
    }

    public function testCursorLowersTheName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('DELETE FROM t WHERE CURRENT OF "C"');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Delete::class, $statement);
        $where = $statement->where;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\CurrentOf::class, $where);
        self::assertSame('C', $where->cursor->value);
    }
}
