<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\WithRule::class)]
#[Small]
final class WithRuleTest extends TestCase
{
    public function testWithIsNullWithoutClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('INSERT INTO t VALUES (1)');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\WithRule($lowering);
        self::assertNull($rule->with($tree->find('opt_with_clause')[0]));
    }

    public function testTablesLowersTheStatementThroughTheManipulationFamily(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('WITH x AS (SELECT 1) SELECT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\WithRule($lowering);
        $this->expectExceptionMessage('PreparableStmt');
        $rule->tables($tree->find('cte_list')[0]);
    }

    public function testMaterializationReadsNotMaterialized(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('WITH x AS NOT MATERIALIZED (SELECT 1) SELECT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\WithRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\Materialization::NotMaterialized, $rule->materialization($tree->find('opt_materialized')[0]));
    }

    public function testSearchReadsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('WITH RECURSIVE x AS (SELECT 1) SEARCH DEPTH FIRST BY a SET s SELECT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\WithRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder::Depth, $rule->search($tree->find('opt_search_clause')[0])?->order);
    }

    public function testCycleReadsTheMarkValues(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('WITH RECURSIVE x AS (SELECT 1) CYCLE a SET m TO 1 DEFAULT 0 USING p SELECT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\WithRule($lowering);
        self::assertNotNull($rule->cycle($tree->find('opt_cycle_clause')[0])?->markDefault);
    }
}
