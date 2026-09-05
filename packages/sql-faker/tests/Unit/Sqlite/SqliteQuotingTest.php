<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\SqliteQuoting;

#[CoversClass(SqliteQuoting::class)]
final class SqliteQuotingTest extends TestCase
{
    #[DataProvider('providerIdentifier')]
    public function testIdentifierEncodesTheChosenBody(string $body, string $expected): void
    {
        self::assertSame($expected, SqliteQuoting::identifier($body));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerIdentifier(): iterable
    {
        yield 'plain' => ['users', '"users"'];
        yield 'keyword' => ['select', '"select"'];
        yield 'embedded delimiter' => ['a"b', '"a""b"'];
        yield 'multiple delimiters' => ['"x"', '"""x"""'];
        yield 'unicode' => ['利用者', '"利用者"'];
    }

    #[DataProvider('providerStringLiteral')]
    public function testStringLiteralEncodesTheChosenBody(string $body, string $expected): void
    {
        self::assertSame($expected, SqliteQuoting::stringLiteral($body));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerStringLiteral(): iterable
    {
        yield 'empty' => ['', '\'\''];
        yield 'plain' => ['text', '\'text\''];
        yield 'apostrophe' => ['a\'b', '\'a\'\'b\''];
        yield 'consecutive quotes' => ['\'\'', '\'\'\'\'\'\''];
        yield 'backslash' => ['a\\b', '\'a\\b\''];
        yield 'unicode' => ['日本語', '\'日本語\''];
    }

    #[DataProvider('providerBacktickIdentifier')]
    public function testBacktickIdentifierEncodesTheChosenBody(string $body, string $expected): void
    {
        self::assertSame($expected, SqliteQuoting::backtickIdentifier($body));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerBacktickIdentifier(): iterable
    {
        yield 'plain' => ['users', '`users`'];
        yield 'embedded' => ['a`b', '`a``b`'];
        yield 'multiple' => ['`a`', '```a```'];
    }

    #[DataProvider('providerBracketIdentifier')]
    public function testBracketIdentifierEncodesTheChosenBody(string $body, string $expected): void
    {
        self::assertSame($expected, SqliteQuoting::bracketIdentifier($body));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerBracketIdentifier(): iterable
    {
        yield 'plain' => ['users', '[users]'];
        yield 'closing bracket' => ['a]b', '[ab]'];
        yield 'multiple closing brackets' => [']]a]', '[a]'];
    }
}
