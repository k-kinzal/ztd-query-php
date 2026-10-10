<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(Constancy::class)]
#[Small]
final class ConstancyTest extends TestCase
{
    public function testColumnKeepsComputedOuterAliasesDependentOnEachRow(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES (10),(-3),(NULL),(7)');
        $result = $session->query('SELECT a+1 AS x FROM t HAVING (SELECT (SELECT SUM(x)))>5 ORDER BY x')[0];
        self::assertInstanceOf(ResultSet::class, $result);

        self::assertSame([['8'], ['11']], $result->rows);
    }

    public function testOfTellsHowLongEachKindOfExpressionStaysTheSame(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT CONCAT('a', DATABASE(), ROW_COUNT(), (SELECT 'a')), CONCAT(USER(), NOW(), @v, (SELECT USER())), CONCAT(SYSDATE()), (@v := 1)");
        $resolved = $operation->field(0)->expression;
        $statement = $operation->field(1)->expression;
        $clock = $operation->field(2)->expression;
        $assignment = $operation->field(3)->expression;
        self::assertNotNull($resolved);
        self::assertNotNull($statement);
        self::assertNotNull($clock);
        self::assertNotNull($assignment);

        self::assertSame([Constancy::Resolved, Constancy::Statement, Constancy::Row, Constancy::Row], [Constancy::of($resolved, $operation->facts), Constancy::of($statement, $operation->facts), Constancy::of($clock, $operation->facts), Constancy::of($assignment, $operation->facts)]);
    }

    public function testOfMakesAnAssignedUserVariableVary(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @V');
        $variable = $operation->field(0)->expression;
        self::assertNotNull($variable);

        self::assertSame([Constancy::Row, Constancy::Statement], [Constancy::of($variable, $operation->facts, true, ['v']), Constancy::of($variable, $operation->facts, true, ['w'])]);
    }

    public function testWithinReadsOnlyOuterColumnsInsideASubquery(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT RAND()');
        $call = $operation->field(0)->expression;
        self::assertNotNull($call);

        self::assertSame([Constancy::Resolved, Constancy::Row], [Constancy::within($call, $operation->facts, 1, true, []), Constancy::within($call, $operation->facts, 0, true, [])]);
    }

    public function testFormTellsHowLongANodeStaysTheSameByItsForm(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT @v, @@sql_mode, CURRENT_USER, RAND(), (@v := 1)');
        $variable = $operation->field(0)->expression;
        $system = $operation->field(1)->expression;
        $keyword = $operation->field(2)->expression;
        $call = $operation->field(3)->expression;
        $assignment = $operation->field(4)->expression;
        self::assertNotNull($variable);
        self::assertNotNull($system);
        self::assertNotNull($keyword);
        self::assertNotNull($call);
        self::assertNotNull($assignment);

        self::assertSame([Constancy::Row, Constancy::Statement, Constancy::Statement, Constancy::Row, Constancy::Row], [Constancy::form($variable, $operation->facts, true, ['v'], true), Constancy::form($system, $operation->facts, true, [], true), Constancy::form($keyword, $operation->facts, true, [], true), Constancy::form($call, $operation->facts, true, [], true), Constancy::form($assignment, $operation->facts, true, [], true)]);
    }

    public function testColumnMakesAColumnOfTheBlockVary(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a');
        $use = $operation->field(0)->expression;

        self::assertInstanceOf(ColumnUse::class, $use);
        self::assertSame([Constancy::Row, Constancy::Resolved], [Constancy::column($use, $operation->facts, 0), Constancy::column($use, $operation->facts, 1)]);
    }

    public function testFunctionTellsTheFunctionsOfTheStatementAndOfEachCall(): void
    {
        self::assertSame([Constancy::Statement, Constancy::Row, Constancy::Row, Constancy::Resolved], [Constancy::function('LAST_INSERT_ID', 0), Constancy::function('LAST_INSERT_ID', 1), Constancy::function('UUID', 0), Constancy::function('LENGTH', 1)]);
    }

    public function testChildrenJoinsTheChildrenOfANode(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT CONCAT(USER(), RAND())');
        $call = $operation->field(0)->expression;
        self::assertNotNull($call);

        self::assertSame(Constancy::Row, Constancy::children($call, $operation->facts, 0, true, []));
    }

    public function testWithinUsesTheReplacementResolvedBySemantics(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze("SELECT (SELECT 'a' LIMIT 0), (SELECT 'a' FROM DUAL WHERE 1), (SELECT 'a' UNION SELECT 'b')");
        $lifetimes = array_map(static function (int $index) use ($operation): Constancy {
            $subquery = $operation->field($index)->expression;
            self::assertInstanceOf(ScalarSubquery::class, $subquery);

            return Constancy::within($subquery, $operation->facts, 0, true, []);
        }, range(0, 2));

        self::assertSame([Constancy::Resolved, Constancy::Statement, Constancy::Statement], $lifetimes);
    }

    public function testJoinAnswersTheShorterLived(): void
    {
        self::assertSame([Constancy::Statement, Constancy::Row, Constancy::Resolved], [Constancy::Resolved->join(Constancy::Statement), Constancy::Row->join(Constancy::Statement), Constancy::Resolved->join(Constancy::Resolved)]);
    }

    public function testConstantTellsWhetherTheValueStaysForTheStatement(): void
    {
        self::assertSame([true, true, false], [Constancy::Resolved->constant(), Constancy::Statement->constant(), Constancy::Row->constant()]);
    }

    public function testOfMakesACorrelatedSubqueryVary(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (id INT, s VARCHAR(5))');
        $session->query("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, NULL)");
        $result = $session->query("SELECT (SELECT u.s FROM t AS u WHERE u.id = t.id) = 0, (SELECT 'a' FROM t AS u LIMIT 1) < 'b' FROM t")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1'], ['1', '1'], [null, '1']], $result->rows);
    }

    public function testOfTakesAnAssignmentAsItsValueWhenAssignmentsDoNotVary(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (@v := 1), (@w := @w + 1)');
        $constant = $operation->field(0)->expression;
        $varying = $operation->field(1)->expression;
        self::assertNotNull($constant);
        self::assertNotNull($varying);

        self::assertSame([Constancy::Statement, Constancy::Row, Constancy::Row], [Constancy::of($constant, $operation->facts, true, ['v', 'w'], false), Constancy::of($varying, $operation->facts, true, ['v', 'w'], false), Constancy::of($constant, $operation->facts, true, ['v', 'w'])]);
    }

    public function testOfKnowsTheAccountFunctionsWhenItResolvesA57Statement(): void
    {
        $operation = (new Instance())->connect()->analyze('SELECT USER(), SESSION_USER()');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $user = $statement->items[0];
        $session = $statement->items[1];
        self::assertInstanceOf(SelectExpression::class, $user);
        self::assertInstanceOf(SelectExpression::class, $session);

        self::assertSame([Constancy::Statement, Constancy::Resolved, Constancy::Statement, Constancy::Resolved], [Constancy::of($user->expression, $operation->facts), Constancy::of($user->expression, $operation->facts, true, [], true, true), Constancy::of($session->expression, $operation->facts), Constancy::of($session->expression, $operation->facts, true, [], true, true)]);
    }

    public function testFormMakesAWindowFunctionVaryByRow(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ROW_NUMBER() OVER ()');
        $call = $operation->field(0)->expression;
        self::assertNotNull($call);

        self::assertSame(Constancy::Row, Constancy::of($call, $operation->facts));
    }
}
