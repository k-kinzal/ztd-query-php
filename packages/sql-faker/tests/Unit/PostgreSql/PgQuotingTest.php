<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\PgQuoting;

#[CoversClass(PgQuoting::class)]
final class PgQuotingTest extends TestCase
{
    #[DataProvider('providerIdentifier')]
    public function testIdentifierEncodesTheChosenBody(string $body, string $expected): void
    {
        self::assertSame($expected, PgQuoting::identifier($body));
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
        self::assertSame($expected, PgQuoting::stringLiteral($body));
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
}
