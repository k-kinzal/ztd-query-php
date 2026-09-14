<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter;

#[CoversClass(MySqlIdentifierQuoter::class)]
final class MySqlIdentifierQuoterTest extends TestCase
{
    public function testQuoteReturnsNonEmptyString(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('users');
        self::assertNotEmpty($result);
    }

    public function testQuoteWrapsWithBackticks(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('users');
        self::assertSame('`users`', $result);
    }

    public function testQuoteSimpleIdentifier(): void
    {
        self::assertSame('`column_name`', (new MySqlIdentifierQuoter())->quote('column_name'));
    }

    public function testQuoteDoesNotDoubleQuoteAlreadyQuoted(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('`users`');
        self::assertSame('`users`', $result);
    }

    public function testQuoteEscapesBacktickInIdentifier(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('col`name');
        self::assertSame('`col``name`', $result);
    }

    public function testQuoteIsDeterministic(): void
    {
        $result1 = (new MySqlIdentifierQuoter())->quote('users');
        $result2 = (new MySqlIdentifierQuoter())->quote('users');
        self::assertSame($result1, $result2);
    }

    public function testQuotedIdentifierContainsOriginalName(): void
    {
        $identifier = 'my_table';
        $result = (new MySqlIdentifierQuoter())->quote($identifier);
        $inner = substr($result, 1, -1);
        $recovered = str_replace('``', '`', $inner);
        self::assertSame($identifier, $recovered);
    }

    public function testQuoteAlreadyQuotedWithEscapedBacktick(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('`col``name`');
        self::assertSame('`col``name`', $result);
    }

    public function testQuoteEmptyStringProducesBacktickPair(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('');
        self::assertSame('``', $result);
    }

    public function testQuoteReservedWord(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('select');
        self::assertSame('`select`', $result);
    }

    public function testQuoteWithSpaces(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('my table');
        self::assertSame('`my table`', $result);
    }

    public function testQuoteEmptyBacktickPairStripsWrapping(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('``');
        self::assertSame('``', $result);
    }

    public function testQuoteWrapsIdentifier(): void
    {
        $quoter = new MySqlIdentifierQuoter();
        $char = '`';
        $result = $quoter->quote('table_name');
        self::assertStringStartsWith($char, $result);
        self::assertStringEndsWith($char, $result);
    }

    public function testQuoteEscapesQuoteCharacterInIdentifier(): void
    {
        $quoter = new MySqlIdentifierQuoter();
        $char = '`';
        $identifier = 'col' . $char . 'name';
        $result = $quoter->quote($identifier);
        self::assertNotEmpty($result);
        self::assertStringStartsWith($char, $result);
        self::assertStringEndsWith($char, $result);
        $simpleQuoted = $char . $identifier . $char;
        self::assertGreaterThanOrEqual(strlen($simpleQuoted), strlen($result), 'Escaped identifier should be at least as long as non-escaped form');
    }

    public function testQuoteProducesExactResult(): void
    {
        $quoter = new MySqlIdentifierQuoter();
        $char = '`';
        $result = $quoter->quote('users');
        self::assertSame($char . 'users' . $char, $result);
    }

    public function testQuoteEscapesEmbeddedQuoteExactly(): void
    {
        $quoter = new MySqlIdentifierQuoter();
        $char = '`';
        $result = $quoter->quote('col' . $char . 'name');
        $expected = $char . 'col' . $char . $char . 'name' . $char;
        self::assertSame($expected, $result);
    }
}
