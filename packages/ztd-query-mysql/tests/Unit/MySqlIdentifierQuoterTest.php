<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use Tests\Contract\IdentifierQuoterContractTest;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;

#[CoversClass(MySqlIdentifierQuoter::class)]
final class MySqlIdentifierQuoterTest extends IdentifierQuoterContractTest
{
    protected function createQuoter(): IdentifierQuoter
    {
        return new MySqlIdentifierQuoter();
    }

    protected function quoteCharacter(): string
    {
        return '`';
    }

    /**
     * P-IQ-1: Non-empty output.
     */
    public function testQuoteReturnsNonEmptyString(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('users');
        self::assertNotEmpty($result);
    }

    /**
     * P-IQ-2: Wrapping with backticks.
     */
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

    /**
     * P-IQ-5: Escape safety - backtick in identifier name.
     */
    public function testQuoteEscapesBacktickInIdentifier(): void
    {
        $result = (new MySqlIdentifierQuoter())->quote('col`name');
        self::assertSame('`col``name`', $result);
    }

    /**
     * P-IQ-4: Determinism.
     */
    public function testQuoteIsDeterministic(): void
    {
        $result1 = (new MySqlIdentifierQuoter())->quote('users');
        $result2 = (new MySqlIdentifierQuoter())->quote('users');
        self::assertSame($result1, $result2);
    }

    /**
     * P-IQ-3: Containment - original name is recoverable.
     */
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
}
