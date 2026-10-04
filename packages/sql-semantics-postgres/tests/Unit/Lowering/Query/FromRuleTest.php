<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule::class)]
#[Medium]
final class FromRuleTest extends TestCase
{
    public function testItemIsAListForSeveralItems(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a, b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\RelationList::class, $rule->item($tree->find('from_clause')[0]));
    }

    public function testItemsIsEmptyWithoutFrom(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertSame([], $rule->items($tree->find('from_clause')[0]));
    }

    public function testReferenceLowersALateralSubquery(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM LATERAL (SELECT 1) x');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        $reference = $rule->reference($tree->find('table_ref')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable::class, $reference);
        self::assertTrue($reference->lateral);
    }

    public function testTableLowersTheSample(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t TABLESAMPLE system (1)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertSame('t', $rule->table($lowering->productions->form($tree->find('table_ref')[0]), null)->name()->name->value);
    }

    public function testDerivedStripsTheParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM (SELECT 1) AS x');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Select::class, $rule->derived($tree->find('select_with_parens')[0], $tree->find('opt_alias_clause')[0], false)->query);
    }

    public function testRenamedJoinKeepsTheAlias(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM (a CROSS JOIN b) AS j');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertSame('j', $rule->renamedJoin($lowering->productions->form($tree->find('table_ref')[0]))->alias?->value);
    }

    public function testJoinedLowersANaturalJoin(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a NATURAL RIGHT JOIN b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        $join = $rule->joined($tree->find('joined_table')[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Join::class, $join);
        self::assertTrue($join->natural);
    }

    public function testKindReadsLeftOuter(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a LEFT OUTER JOIN b ON TRUE');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinKind::Left, $rule->kind($tree->find('join_type')[0]));
    }

    public function testQualificationReadsUsing(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM a JOIN b USING (x)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Relation\JoinUsing::class, $rule->qualification($tree->find('join_qual')[0]));
    }

    public function testAliasReadsTheColumns(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t x (p, q)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertCount(2, $rule->alias($tree->find('opt_alias_clause')[0])[1]);
    }

    public function testFunctionsLowersRowsFrom(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM ROWS FROM (f(), g()) WITH ORDINALITY');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        $table = $rule->functions($tree->find('func_table')[0], $tree->find('func_alias_clause')[0], false);
        self::assertSame([2, true], [count($table->functions), $table->ordinality]);
    }

    public function testRowsFromReadsTheDefinitions(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM ROWS FROM (f() AS (a int))');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertCount(1, $rule->rowsFrom($tree->find('rowsfrom_list')[0])[0]->definitions);
    }

    public function testFunctionAliasReadsDefinitionsWithoutAlias(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM f() AS (a int)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertSame(null, $rule->functionAlias($tree->find('func_alias_clause')[0])[0]);
    }

    public function testOrdinalityIsFalseWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM f()');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertFalse($rule->ordinality($tree->find('opt_ordinality')[0]));
    }

    public function testRelationReadsOnlyInParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM ONLY (t)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertTrue($rule->relation($tree->find('relation_expr')[0])->only);
    }

    public function testRelationsLowersTheList(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('TRUNCATE a, b');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertCount(2, $rule->relations($tree->find('relation_expr_list')[0]));
    }

    public function testSampleReadsTheSeed(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t TABLESAMPLE system (1) REPEATABLE (2)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\FromRule($lowering);
        self::assertNotNull($rule->sample($tree->find('tablesample_clause')[0])->seed);
    }
}
