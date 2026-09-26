<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Core\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Core\Compact\Visitor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
final class ReductionsTest extends TestCase
{
    public function testCandidatesProtectsHintBearingGrouping(): void
    {
        $parser = new \SqlParser\Sqlite\SqliteParser();
        $tree = $parser->parse('SELECT (a) AS x');
        self::assertSame([[7, 9], [11]], (new \SqlFormatter\Core\Compact\Reductions(\SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->grouping, \SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->aliases))->candidates($tree, []));
        self::assertSame([[11]], (new \SqlFormatter\Core\Compact\Reductions(\SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->grouping, \SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->aliases))->candidates($tree, [8 => true]));
    }

    public function testAliasesKeepsCastAndCteAs(): void
    {
        $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
        $tree = $parser->parse('WITH q AS (SELECT CAST(a AS text) AS x FROM t AS u) SELECT x FROM q');
        self::assertSame([34, 46], (new \SqlFormatter\Core\Compact\Reductions(\SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->grouping, \SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->aliases))->aliases($tree));
    }
}
