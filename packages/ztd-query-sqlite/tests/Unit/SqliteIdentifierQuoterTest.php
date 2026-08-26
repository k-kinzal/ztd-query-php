<?php

declare(strict_types=1);

namespace Tests\Unit;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Contract\IdentifierQuoterContractTest;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\Sqlite\SqliteIdentifierQuoter;

#[CoversClass(SqliteIdentifierQuoter::class)]
final class SqliteIdentifierQuoterTest extends IdentifierQuoterContractTest
{
    public function createQuoter(): IdentifierQuoter
    {
        return new SqliteIdentifierQuoter();
    }

    public function quoteCharacter(): string
    {
        return '"';
    }

    public function testQuoteSimpleIdentifier(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('"users"', $quoter->quote('users'));
    }

    public function testQuoteIdentifierWithSpaces(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('"my table"', $quoter->quote('my table'));
    }

    public function testQuoteIdentifierWithDoubleQuotes(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('"col""name"', $quoter->quote('col"name'));
    }

    public function testQuoteAlreadyQuotedIdentifier(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('"users"', $quoter->quote('"users"'));
    }

    public function testQuoteAlreadyQuotedWithEscapedQuotes(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('"col""name"', $quoter->quote('"col""name"'));
    }

    #[Override]
    public function testQuoteReturnsNonEmptyString(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        $result = $quoter->quote('x');
        self::assertNotEmpty($result);
    }

    public function testQuoteStartsAndEndsWithDoubleQuote(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        $result = $quoter->quote('test_table');
        self::assertStringStartsWith('"', $result);
        self::assertStringEndsWith('"', $result);
    }

    public function testDeterminism(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        $result1 = $quoter->quote('users');
        $result2 = $quoter->quote('users');
        self::assertSame($result1, $result2);
    }

    public function testContainment(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        $original = 'my_table';
        $result = $quoter->quote($original);
        self::assertStringContainsString($original, $result);
    }

    public function testQuoteEmptyQuotedIdentifier(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('""', $quoter->quote('""'));
    }

    public function testQuoteSingleCharIdentifier(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('"x"', $quoter->quote('x'));
    }

    public function testQuoteSingleDoubleQuote(): void
    {
        $quoter = new SqliteIdentifierQuoter();
        self::assertSame('""""', $quoter->quote('"'));
    }
}
