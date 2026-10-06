<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule::class)]
#[Medium]
final class CreateAsRuleTest extends TestCase
{
    public function testTableAsLowersTheQuery(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE IF NOT EXISTS t AS SELECT 1 WITH DATA');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->tableAs($tree->find('CreateAsStmt')[0]);
        self::assertSame([
          0 => true,
          1 => true,
        ], [$value->ifNotExists, $value->withData]);
    }

    public function testTableAsExecuteBuildsTheStatement(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE IF NOT EXISTS t AS EXECUTE q');
        $execute = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('EXECUTE q')->statement;
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->tableAsExecute($lowering->productions->form($tree->find('ExecuteStmt')[0]), $execute);
        self::assertSame(true, $value->ifNotExists);
    }

    public function testTargetLowersTheColumnNames(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (x, y) AS SELECT 1, 2');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->target($tree->find('create_as_target')[0]);
        self::assertSame(2, count($value->columns));
    }

    public function testWithDataIsFalseForWithNoData(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t AS SELECT 1 WITH NO DATA');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->withData($tree->find('opt_with_data')[0]);
        self::assertSame(false, $value);
    }

    public function testMaterializedViewLowersUnlogged(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE UNLOGGED MATERIALIZED VIEW m AS SELECT 1');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->materializedView($tree->find('CreateMatViewStmt')[0]);
        self::assertSame(true, $value->unlogged);
    }

    public function testRefreshLowersConcurrently(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('REFRESH MATERIALIZED VIEW CONCURRENTLY m');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->refresh($tree->find('RefreshMatViewStmt')[0]);
        self::assertSame(true, $value->concurrently);
    }

    public function testViewLowersTheCheckOption(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE OR REPLACE RECURSIVE VIEW v (n) AS SELECT 1 WITH LOCAL CHECK OPTION');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateAsRule($lowering))->view($tree->find('ViewStmt')[0]);
        self::assertSame([
          0 => true,
          1 => true,
          2 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Table\View\CheckOption::Local,
        ], [$value->replace, $value->recursive, $value->checkOption]);
    }
}
