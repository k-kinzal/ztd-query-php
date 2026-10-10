<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Command\Output;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Combine\RecursiveUnion;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Recursion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Statement\Query;

#[CoversClass(Recursion::class)]
#[Small]
final class RecursionTest extends TestCase
{
    public function testPlanComputesARecursiveExpressionToItsFixedPoint(): void
    {
        $session = (new Instance())->connect();
        $session->query('SET SESSION cte_max_recursion_depth = 7');
        $operation = $session->analyze('WITH RECURSIVE r (n) AS (SELECT 1 UNION SELECT n + 1 FROM r WHERE n < 3) SELECT n FROM r');
        $statement = $operation->statement;
        self::assertInstanceOf(QueryExpression::class, $statement);
        $with = $statement->with;
        self::assertInstanceOf(With::class, $with);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        $plan = (new Recursion($planner))->plan($with->tables[0], null);

        self::assertNotNull($plan);
        self::assertInstanceOf(RecursiveUnion::class, $plan->root);
        self::assertTrue($plan->root->distinct);
        self::assertSame(7, $plan->root->limit);
        self::assertSame(['n'], $plan->names);
        self::assertTrue($plan->domains[0]->nullable);
        self::assertSame([], $planner->recursions);
        self::assertSame([['1'], ['2'], ['3']], (new Output())->result($plan, $context)->rows);
    }

    public function testPlanAnswersNullForAnExpressionThatDoesNotReferToItself(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH RECURSIVE c AS (SELECT 1 AS n UNION ALL SELECT 2) SELECT n FROM c');
        $statement = $operation->statement;
        self::assertInstanceOf(QueryExpression::class, $statement);
        $with = $statement->with;
        self::assertInstanceOf(With::class, $with);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);

        self::assertNull((new Recursion($planner))->plan($with->tables[0], null));
    }

    public function testPlanNamesTheColumnsAfterTheNonrecursivePartWithoutAColumnList(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('WITH RECURSIVE r AS (SELECT 1 AS k UNION ALL SELECT k + 1 FROM r WHERE k < 2) SELECT * FROM r')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame('k', $result->columns[0]->name);
        self::assertSame([['1'], ['2']], $result->rows);
    }

    public function testRefersTellsWhetherAQueryReadsTheExpression(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH RECURSIVE r (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM r WHERE n < 3) SELECT n FROM r');
        $statement = $operation->statement;
        self::assertInstanceOf(QueryExpression::class, $statement);
        $with = $statement->with;
        self::assertInstanceOf(With::class, $with);
        $union = $with->tables[0]->query;
        self::assertInstanceOf(SetOperation::class, $union);
        self::assertInstanceOf(Query::class, $union->left);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $recursion = new Recursion(new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary));

        self::assertFalse($recursion->refers($union->left, $with->tables[0]));
        self::assertTrue($recursion->refers($union->right, $with->tables[0]));
    }
}
