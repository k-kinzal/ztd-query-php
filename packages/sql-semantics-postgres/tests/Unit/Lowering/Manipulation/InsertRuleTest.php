<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\InsertRule::class)]
#[Medium]
final class InsertRuleTest extends TestCase
{
    public function testInsertLowersDefaultValues(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t DEFAULT VALUES');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertDefaults::class, $query->statement);
    }

    public function testRestLowersTheColumnsAndTheOverride(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t (a, b) OVERRIDING USER VALUE SELECT 1, 2');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect::class, $statement);
        self::assertSame([2, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding::User], [count($statement->columns), $statement->overriding]);
    }

    public function testRowsMakesValuesInParenthesesRows(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t (a) ((VALUES (1)))');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, $query->statement);
    }

    public function testRowsLeavesValuesWithClausesAQuery(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t VALUES (1) LIMIT 1');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertSelect::class, $query->statement);
    }

    public function testTargetLowersTheAlias(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO s.t AS x VALUES (1)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, $statement);
        self::assertSame(['s', 'x'], [$statement->target->name()->schema?->value, $statement->target->alias()?->value]);
    }

    public function testOverridingLowersSystemValue(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t OVERRIDING SYSTEM VALUE VALUES (1)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding::System, $statement->overriding);
    }

    public function testColumnsLowersTheSteps(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t (a[1:2], b.c) VALUES (1, 2)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, $statement);
        self::assertSame([1, 1], [count($statement->columns[0]->steps), count($statement->columns[1]->steps)]);
    }

    public function testConflictLowersDoUpdate(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t VALUES (1) ON CONFLICT (a) DO UPDATE SET b = 2');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, $query->statement);
    }

    public function testInferenceLowersTheConstraintName(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('INSERT INTO t VALUES (1) ON CONFLICT ON CONSTRAINT k DO NOTHING');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\InsertRows::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Conflict\ConstraintInference::class, $statement->conflict?->target);
    }
}
