<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries::class)]
#[Medium]
final class QueriesTest extends TestCase
{
    public function testStatementLowersASelectStatement(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT a FROM t');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $lowering->queries->statement($tree->find('SelectStmt')[0]));
    }

    public function testQueryLowersASubquery(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT (VALUES (1))');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList::class, $lowering->queries->query($tree->find('select_with_parens')[0]));
    }

    public function testSortClauseKeepsIntegerConstants(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant::class, $lowering->queries->sortClause($tree->find('sort_clause')[0])[0]->expression);
    }

    public function testSortDirectionReadsDesc(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 DESC');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection::Descending, $lowering->queries->sortDirection($tree->find('opt_asc_desc')[0]));
    }

    public function testNullsOrderReadsFirst(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 NULLS FIRST');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder::First, $lowering->queries->nullsOrder($tree->find('opt_nulls_order')[0]));
    }

    public function testWhereLowersThePredicate(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 WHERE TRUE');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertNotNull($lowering->queries->where($tree->find('where_clause')[0]));
    }

    public function testFromLowersEveryItem(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a, b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertCount(2, $lowering->queries->from($tree->find('from_clause')[0]));
    }

    public function testFromItemIsTheOnlyItem(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\TableInput::class, $lowering->queries->fromItem($tree->find('from_clause')[0]));
    }

    public function testRelationReadsTheStar(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t *');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertFalse($lowering->queries->relation($tree->find('relation_expr')[0])->only);
    }

    public function testTableReferenceLowersAJoin(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a JOIN b ON TRUE');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Join::class, $lowering->queries->tableReference($tree->find('table_ref')[0]));
    }

    public function testRelationsLowersTheList(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('LOCK a, b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertCount(2, $lowering->queries->relations($tree->find('relation_expr_list')[0]));
    }

    public function testTargetsLowersTheList(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1, 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertCount(2, $lowering->queries->targets($tree->find('target_list')[0]));
    }

    public function testWithIsNullWithoutClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('INSERT INTO t VALUES (1)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\Queries($lowering);
        self::assertNull($lowering->queries->with($tree->find('opt_with_clause')[0]));
    }
}
