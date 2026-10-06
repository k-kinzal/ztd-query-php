<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule::class)]
#[Medium]
final class IndexRuleTest extends TestCase
{
    public function testStatementLowersAnIndexWithIfNotExists(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX IF NOT EXISTS i ON t (a)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->statement($tree->find('IndexStmt')[0]);
        self::assertSame([
          0 => true,
          1 => 'i',
        ], [$value->ifNotExists, $value->name?->value]);
    }

    public function testUniqueIsTrueForUnique(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE UNIQUE INDEX ON t (a)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->unique($tree->find('opt_unique')[0]);
        self::assertSame(true, $value);
    }

    public function testMethodLowersTheAccessMethod(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t USING hash (a)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->method($tree->find('access_method_clause')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $n1);
        self::assertSame('hash', $n1->value);
    }

    public function testTablespaceIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->tablespace($tree->find('OptTableSpace')[0]);
        self::assertSame(null, $value);
    }

    public function testPredicateIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int, EXCLUDE (a WITH =))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->predicate($tree->find('OptWhereClause')[0]);
        self::assertSame(null, $value);
    }

    public function testIndexParametersLowersEveryKey(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a, b, c)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->indexParameters($tree->find('index_params')[0]);
        self::assertSame(3, count($value));
    }

    public function testIncludedLowersTheIncludedColumns(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a) INCLUDE (b, c)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->included($tree->find('opt_include')[0]);
        self::assertSame(2, count($value));
    }

    public function testElementKeepsParentheses(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t ((a))');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->element($tree->find('index_elem')[0]);
        $n1 = $value->key;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey::class, $n1);
        self::assertSame(true, $n1->parenthesized);
    }

    public function testOptionsLowersTheOperatorClassParameters(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE INDEX ON t (a ops (x = 1) DESC)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\IndexRule($lowering))->element($tree->find('index_elem')[0]);
        self::assertSame([
          0 => 1,
          1 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection::Descending,
        ], [count($value->classOptions), $value->direction]);
    }
}
