<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule::class)]
#[Medium]
final class LimitRuleTest extends TestCase
{
    public function testLimitKeepsTheOffsetFirst(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 OFFSET 1 LIMIT 2');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertTrue($rule->limit($tree->find('select_limit')[0])?->offsetFirst);
    }

    public function testCountLowersLimitAll(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 LIMIT ALL');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\LimitCount::class, $rule->count($tree->find('limit_clause')[0]));
    }

    public function testFetchReadsWithTies(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FETCH NEXT ROWS WITH TIES');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertTrue($rule->fetch($lowering->productions->form($tree->find('limit_clause')[0]), null, true, 2)->withTies);
    }

    public function testOffsetLowersTheStart(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 OFFSET 5');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant::class, $rule->offset($tree->find('offset_clause')[0])->start);
    }

    public function testRowsOffsetLowersTheStandardSpelling(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 OFFSET 5 ROWS');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant::class, $rule->rowsOffset($lowering->productions->form($tree->find('offset_clause')[0]))->start);
    }

    public function testRowsClaimsTheNoiseWord(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 OFFSET 5 ROW');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        $rule->rows($tree->find('row_or_rows')[0]);
        self::assertCount(1, $tree->find('row_or_rows'));
    }

    public function testNoiseRefusesAnotherProduction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 OFFSET 5 ROW');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        $this->expectExceptionMessage('No semantic rule is implemented for: row_or_rows: ROW');
        $rule->noise($tree->find('row_or_rows')[0], 'row_or_rows: ROWS');
    }

    public function testLimitValueIsNullForAll(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 LIMIT ALL');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertNull($rule->limitValue($tree->find('select_limit_value')[0]));
    }

    public function testOffsetValueLowersTheExpression(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 OFFSET 1');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant::class, $rule->offsetValue($tree->find('select_offset_value')[0]));
    }

    public function testFetchValueNegatesTheConstant(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FETCH FIRST - 2 ROWS ONLY');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Expression\Operator\UnaryOperation::class, $rule->fetchValue($tree->find('select_fetch_first_value')[0]));
    }

    public function testLockingReadsReadOnly(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FOR READ ONLY');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertSame([[], true], $rule->locking($tree->find('for_locking_clause')[0]));
    }

    public function testItemsLowersEveryClause(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FOR UPDATE FOR SHARE OF t');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertCount(2, $rule->items($tree->find('for_locking_items')[0]));
    }

    public function testWaitReadsSkipLocked(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FOR UPDATE SKIP LOCKED');
        $rule = new \SqlSemantics\Platform\PostgreSql\Lowering\Query\LimitRule($lowering);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\LockWait::SkipLocked, $rule->wait($tree->find('opt_nowait_or_skip')[0]));
    }
}
