<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\Blocks;
use MySqlMemory\Hint\QueryBlock;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Blocks::class)]
#[Small]
final class BlocksTest extends TestCase
{
    public function testReadNumbersBlocksAsWrittenAndOrdersHowTheyAreRead(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, KEY ka (a))');
        $blocks = (new Blocks($session->instance->dictionary, 'd'))->read($session->analyze('SELECT (SELECT 1) FROM t JOIN (SELECT 1 a) d ON d.a IN (SELECT 2) WHERE 1 IN (SELECT 3)')->statement);

        self::assertSame([1, 2, 3, 4, 5], array_keys($blocks->blocks));
        self::assertSame([2, 3, 4, 5, 1], $blocks->contextualized);
        self::assertSame([1, 3, 2, 5, 4], $blocks->resolved);
        self::assertSame([['t', ['ka']], ['d', []]], $blocks->blocks[1]->tables);
    }

    public function testReadLooksIntoTheStatementOfExplainAndAnswersNoBlocksForOtherStatements(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([1], (new Blocks($session->instance->dictionary, ''))->read($session->analyze('EXPLAIN SELECT 1')->statement)->resolved);
        self::assertSame([], (new Blocks($session->instance->dictionary, ''))->read($session->analyze('SHOW TABLES FROM mysql')->statement)->blocks);
    }

    public function testReadGivesSetItsOwnBlock(): void
    {
        $session = (new Instance())->connect();

        self::assertSame([1, 2], (new Blocks($session->instance->dictionary, ''))->read($session->analyze('SET @x = (SELECT /*+ BKA(x) */ 1)')->statement)->resolved);
    }

    public function testInsertSharesBlock1WithTheFirstBlockOfItsQuery(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('INSERT /*+ BKA(i) */ INTO t SELECT /*+ BKA(s) */ a FROM u UNION SELECT 2')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(InsertQuery::class, $statement);
        $blocks->insert($statement);
        self::assertSame(['BKA(`s`)', 'BKA(`i`)'], array_map(static fn ($hint): string => $hint->text(), $blocks->blocks[1]->hints));
        self::assertSame([['t', []], ['u', []]], $blocks->blocks[1]->tables);
        self::assertSame([1, 2], $blocks->resolved);
    }

    public function testChangeReadsTheTablesOfUpdate(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('UPDATE /*+ BKA(t) */ t, u SET t.a = (SELECT 1)')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(Update::class, $statement);
        $blocks->change($statement);
        self::assertSame([['t', []], ['u', []]], $blocks->blocks[1]->tables);
        self::assertSame([1, 2], $blocks->resolved);
    }

    public function testQueryReadsAReferenceToACommonTableExpressionAsANewBlock(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('WITH c AS (SELECT 1) SELECT * FROM c, c AS c2')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(QueryExpression::class, $statement);
        self::assertSame([1, 2, 3], $blocks->query($statement, true));
        self::assertTrue($blocks->blocks[1]->top);
        self::assertSame([['c', []], ['c2', []]], $blocks->blocks[1]->tables);
    }

    public function testSelectPutsTheHintsOfTheBlockFirst(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT /*+ QB_NAME(q) */ 1')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(Select::class, $statement);
        self::assertSame([1], $blocks->select($statement, false));
        self::assertSame('QB_NAME(`q`)', $blocks->blocks[1]->hints[0]->text());
    }

    public function testRelationAnswersTheBlocksOfDerivedTablesAndOfOnConditions(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT 1 FROM (SELECT 1 a) d JOIN JSON_TABLE(\'[]\', \'$[*]\' COLUMNS (b INT PATH \'$\')) j ON j.b IN (SELECT 2)')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');
        $block = $blocks->open(true);

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(JoinedTable::class, $statement->from);
        self::assertSame([[2], [3]], $blocks->relation($statement->from, $block));
        self::assertSame([['d', []], ['j', []]], $block->tables);
    }

    public function testSubqueriesReadTheQueriesInsideNodes(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('SELECT (SELECT 1), (SELECT 2)')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(Select::class, $statement);
        self::assertSame([1, 2], $blocks->subqueries(...$statement->items));
        self::assertSame([], $blocks->subqueries(null));
    }

    public function testOpenAnswersTheSharedBlockOnce(): void
    {
        $blocks = new Blocks((new Instance())->dictionary, '');
        $shared = new QueryBlock(1);
        $blocks->shared = $shared;

        self::assertSame([$shared, 1], [$blocks->open(false), $blocks->open(false)->number]);
    }

    public function testNamedAnswersTheIndexesOfABaseTable(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('CREATE TABLE d.t (a INT PRIMARY KEY, b INT, KEY kb (b))');

        self::assertSame([['x', ['PRIMARY', 'kb']], ['v', []]], [(new Blocks($session->instance->dictionary, 'd'))->named(new QualifiedName(new Name('t')), 'x'), (new Blocks($session->instance->dictionary, 'd'))->named(new QualifiedName(new Name('v')), null)]);
    }

    public function testCommonFindsTheExpressionInScopeUnlessItIsBeingRead(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('WITH c AS (SELECT 1) SELECT 1')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(QueryExpression::class, $statement);
        self::assertInstanceOf(With::class, $statement->with);
        $blocks->enter($statement->with);
        $cte = $blocks->common('c');
        self::assertNotNull($cte);
        $blocks->expanding[spl_object_id($cte)] = true;
        self::assertSame([null, null], [$blocks->common('c'), $blocks->common('C')]);
    }

    public function testEnterBringsTheExpressionsIntoScope(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('WITH c AS (SELECT 1), e AS (SELECT 2) SELECT 1')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(QueryExpression::class, $statement);
        $blocks->enter($statement->with);
        $blocks->enter(null);

        self::assertSame([['c', 'e']], array_map(array_keys(...), $blocks->scopes));
    }

    public function testLeaveTakesTheExpressionsOutOfScope(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->analyze('WITH c AS (SELECT 1) SELECT 1')->statement;
        $blocks = new Blocks($session->instance->dictionary, '');

        self::assertInstanceOf(QueryExpression::class, $statement);
        $blocks->enter($statement->with);
        $inside = count($blocks->scopes);
        $blocks->leave($statement->with);

        self::assertSame([1, 0], [$inside, count($blocks->scopes)]);
    }
}
