<?php

declare(strict_types=1);

namespace Tests\Unit\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
final class GroupingTest extends TestCase
{
    /**
     * @param class-string<\SqlParser\MySql\MySqlParser|\SqlParser\PostgreSql\PostgreSqlParser|\SqlParser\Sqlite\SqliteParser> $parserClass
     */
    #[DataProvider('providerParsers')]
    public function testPairsDistinguishesGroupingFromOtherParentheses(string $parserClass): void
    {
        $parser = new $parserClass();
        $tree = $parser->parse('SELECT f((a+b)), (SELECT a FROM t), ((a)) FROM t');
        self::assertCount(3, \SqlFormatter\Compact\Grouping::pairs($tree));
    }
    /**
     * @return list<array{class-string<\SqlParser\MySql\MySqlParser|\SqlParser\PostgreSql\PostgreSqlParser|\SqlParser\Sqlite\SqliteParser>}>
     */
    public static function providerParsers(): array
    {
        return [[\SqlParser\MySql\MySqlParser::class], [\SqlParser\PostgreSql\PostgreSqlParser::class], [\SqlParser\Sqlite\SqliteParser::class]];
    }
}
