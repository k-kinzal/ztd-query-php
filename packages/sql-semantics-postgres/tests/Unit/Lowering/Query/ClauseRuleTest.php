<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule::class)]
#[Medium]
final class ClauseRuleTest extends TestCase
{
    public function testOptionsAnswersNullWithoutClauses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('(SELECT 1) LIMIT 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertNull($rule->options($tree->find('opt_sort_clause')[0], null, null, false));
    }

    public function testPositionedWrapsIntegerConstants(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $rule->positioned([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))], \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause::GroupBy)[0]);
    }

    public function testSortClauseKeepsConstants(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 USING <, 2 DESC NULLS FIRST');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        $items = $rule->sortClause($tree->find('sort_clause')[0]);
        self::assertSame([2, '<', \SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder::First], [count($items), $items[0]->using?->name->value, $items[1]->nulls]);
    }

    public function testSortDirectionIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertNull($rule->sortDirection($tree->find('opt_asc_desc')[0]));
    }

    public function testNullsOrderReadsLast(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 NULLS LAST');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder::Last, $rule->nullsOrder($tree->find('opt_nulls_order')[0]));
    }

    public function testWhereIsNullWithoutClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertNull($rule->where($tree->find('where_clause')[0]));
    }

    public function testHavingLowersThePredicate(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 HAVING TRUE');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertNotNull($rule->having($tree->find('having_clause')[0]));
    }

    public function testGroupReadsTheQuantifier(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 GROUP BY ALL a');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetQuantifier::All, $rule->group($tree->find('group_clause')[0])[0]);
    }

    public function testGroupItemsLowersSets(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 GROUP BY (), a');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertCount(2, $rule->groupItems($tree->find('group_by_list')[0]));
    }

    public function testGroupingSetLowersCube(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 GROUP BY CUBE (a, b)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertCount(2, $rule->groupingSet($tree->find('cube_clause')[0])->members);
    }

    public function testWindowsLowersTheDefinitions(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 WINDOW w AS (), v AS ()');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering);
        self::assertCount(2, $rule->windows($tree->find('window_clause')[0]));
    }

    public function testGroupingTermsFlattensAnImplicitRowInParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $one = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'));
        $two = new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2'));
        $row = new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped(new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor([$one, $two], \SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowSpelling::Implicit));
        $term = (new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering))->groupingTerms([$row])[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingRow::class, $term);
        $inner = $term->members[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingRow::class, $inner);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $inner->members[1]);
    }

    public function testGroupingTermsKeepsAnExplicitRow(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $row = new \SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\RowConstructor([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1'))]);
        self::assertSame([$row], (new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering))->groupingTerms([$row]));
    }
}
