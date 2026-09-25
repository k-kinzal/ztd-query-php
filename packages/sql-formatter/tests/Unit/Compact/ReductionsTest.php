<?php

declare(strict_types=1);

namespace Tests\Unit\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFormatter\Compact\Reductions::class)]
#[CoversClass(\SqlFormatter\Compact\Document::class)]
#[CoversClass(\SqlFormatter\Compact\Grouping::class)]
#[CoversClass(\SqlFormatter\Compact\Keywords::class)]
#[CoversClass(\SqlFormatter\Compact\Renderer::class)]
#[CoversClass(\SqlFormatter\Compact\Rules::class)]
#[CoversClass(\SqlFormatter\Compact\Shape::class)]
#[CoversClass(\SqlFormatter\Compact\Spacing::class)]
#[CoversClass(\SqlFormatter\Compact\Trivia::class)]
#[CoversClass(\SqlFormatter\Compact\Visitor::class)]
final class ReductionsTest extends TestCase
{
    public function testCandidatesProtectsHintBearingGrouping(): void
    {
        $parser = new \SqlParser\Sqlite\SqliteParser();
        $tree = $parser->parse('SELECT (a) AS x');
        self::assertSame([[7, 9], [11]], \SqlFormatter\Compact\Reductions::candidates($tree, []));
        self::assertSame([[11]], \SqlFormatter\Compact\Reductions::candidates($tree, [8 => true]));
    }

    public function testAliasesKeepsCastAndCteAs(): void
    {
        $parser = new \SqlParser\PostgreSql\PostgreSqlParser();
        $tree = $parser->parse('WITH q AS (SELECT CAST(a AS text) AS x FROM t AS u) SELECT x FROM q');
        self::assertSame([34, 46], \SqlFormatter\Compact\Reductions::aliases($tree));
    }
}
