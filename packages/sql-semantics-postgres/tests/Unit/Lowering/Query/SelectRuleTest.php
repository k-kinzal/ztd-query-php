<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule::class)]
#[Medium]
final class SelectRuleTest extends TestCase
{
    public function testStatementKeepsTopLevelParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('(SELECT 1)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery::class, $rule->statement($tree->find('SelectStmt')[0]));
    }

    public function testQueryStripsOneLayerOfParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT ((SELECT 1))');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery::class, $rule->query($tree->find('select_with_parens')[0]));
    }

    public function testWithParensAnswersTheQueryInside(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT (SELECT 1)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $rule->withParens($tree->find('select_with_parens')[0]));
    }

    public function testNoParensHandsOptionsToTheSelection(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 ORDER BY 1 LIMIT 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        $query = $rule->noParens($tree->find('select_no_parens')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $query);
        self::assertNotNull($query->options);
    }

    public function testAttachWrapsASetOperation(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 UNION SELECT 2 ORDER BY 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        $options = (new \SqlSemantics\Platform\PostgreSql\Lowering\Query\ClauseRule($lowering))->options($tree->find('sort_clause')[0], null, null, false);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression::class, $rule->attach(null, $tree->find('select_clause')[0], $options));
    }

    public function testClauseTellsWhetherTheOptionsWereTaken(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('(SELECT 1) ORDER BY 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertFalse($rule->clause($tree->find('select_clause')[0], null)[1]);
    }

    public function testSimpleLowersASetOperation(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 INTERSECT ALL SELECT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation::class, $rule->simple($tree->find('simple_select')[0], null)[0]);
    }

    public function testSelectionLowersEveryClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT DISTINCT ON (1) a INTO n FROM t WHERE a GROUP BY a HAVING a WINDOW w AS ()');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        $select = $rule->selection($lowering->productions->form($tree->find('simple_select')[0]), $rule->distinct($tree->find('distinct_clause')[0]), null);
        self::assertSame(['n', 1, 1], [$select->into?->table->name->value, count($select->groupBy), count($select->windows)]);
    }

    public function testDistinctWrapsAPosition(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT DISTINCT ON (1) 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition::class, $rule->distinct($tree->find('distinct_clause')[0])?->on[0]);
    }

    public function testQuantifierIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 UNION SELECT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertNull($rule->quantifier($tree->find('set_quantifier')[0]));
    }

    public function testIntoKeepsThePersistence(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 INTO UNLOGGED TABLE n');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::Unlogged, $rule->into($tree->find('into_clause')[0])?->persistence);
    }

    public function testValuesKeepsTheRowsInOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('VALUES (1), (2, 3), (4)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertSame([1, 2, 1], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow $row): int => count($row->values), $rule->values($tree->find('values_clause')[0])->rows));
    }

    public function testTargetsLowersTheStar(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT *, 1 x');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\SelectRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\StarTarget::class, $rule->targets($tree->find('opt_target_list')[0])[0]);
    }
}
