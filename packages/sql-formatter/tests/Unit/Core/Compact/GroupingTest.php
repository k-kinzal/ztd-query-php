<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Compact;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertCount(3, \SqlFormatter\Facade\DialectFactory::forParser($parser)->compactRules()->grouping->pairs($tree));
    }
    /**
     * @return list<array{class-string<\SqlParser\MySql\MySqlParser|\SqlParser\PostgreSql\PostgreSqlParser|\SqlParser\Sqlite\SqliteParser>}>
     */
    public static function providerParsers(): array
    {
        return [[\SqlParser\MySql\MySqlParser::class], [\SqlParser\PostgreSql\PostgreSqlParser::class], [\SqlParser\Sqlite\SqliteParser::class]];
    }
}
