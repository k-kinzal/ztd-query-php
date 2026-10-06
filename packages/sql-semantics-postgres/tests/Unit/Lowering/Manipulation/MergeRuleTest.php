<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\MergeRule::class)]
#[Medium]
final class MergeRuleTest extends TestCase
{
    public function testMergeLowersReturningOfPostgreSql17(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('MERGE INTO t USING u ON true WHEN MATCHED THEN DELETE RETURNING *');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement);
        self::assertCount(1, $statement->returning);
    }

    public function testClauseLowersTheKindsOfPostgreSql16(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-16.6');
        $query = $semantics->analyze('MERGE INTO t USING u ON true WHEN MATCHED THEN DO NOTHING WHEN NOT MATCHED THEN DO NOTHING');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement);
        self::assertSame([\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::Matched, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeMatch::NotMatched], [$statement->clauses[0]->match, $statement->clauses[1]->match]);
    }

    public function testConditionLowersTheExpression(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('MERGE INTO t USING u ON true WHEN NOT MATCHED BY SOURCE AND true THEN DELETE');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement);
        self::assertNotNull($statement->clauses[0]->condition);
    }

    public function testActionLowersEveryInsertForm(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('MERGE INTO t USING u ON true WHEN NOT MATCHED THEN INSERT (a, b) OVERRIDING SYSTEM VALUE VALUES (1, 2)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement);
        $action = $statement->clauses[0]->action;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsert::class, $action);
        self::assertSame([2, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding::System], [count($action->columns), $action->overriding]);
    }

    public function testValuesLowersTheExpressions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-17.2');
        $query = $semantics->analyze('MERGE INTO t USING u ON true WHEN NOT MATCHED THEN INSERT (a, b) VALUES (1, DEFAULT)');
        $statement = $query->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge::class, $statement);
        $action = $statement->clauses[0]->action;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsert::class, $action);
        self::assertCount(2, $action->values);
    }
}
