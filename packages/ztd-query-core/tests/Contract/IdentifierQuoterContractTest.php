<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\IdentifierQuoter;

/**
 * Abstract contract test for IdentifierQuoter implementations.
 *
 * Enforces contracts defined in quality-standards.md Section 1.8 and properties P-IQ-1 through P-IQ-5.
 */
abstract class IdentifierQuoterContractTest extends TestCase
{
    abstract protected function createQuoter(): IdentifierQuoter;

    /**
     * Return the expected quote character for this platform.
     * MySQL: backtick (`), PostgreSQL/SQLite: double quote (").
     *
     * @return non-empty-string
     */
    abstract protected function quoteCharacter(): string;

    /**
     * quote() must return a non-empty string (P-IQ-1).
     */
    public function testQuoteReturnsNonEmptyString(): void
    {
        $quoter = $this->createQuoter();

        $result = $quoter->quote('users');

        self::assertNotEmpty($result);
    }

    /**
     * Output must start and end with the platform's quoting character (P-IQ-2).
     */
    public function testQuoteWrapsIdentifier(): void
    {
        $quoter = $this->createQuoter();
        $char = $this->quoteCharacter();

        $result = $quoter->quote('table_name');

        self::assertStringStartsWith($char, $result);
        self::assertStringEndsWith($char, $result);
    }

    /**
     * If the identifier contains the quoting character, the output must still be valid (P-IQ-5).
     */
    public function testQuoteEscapesQuoteCharacterInIdentifier(): void
    {
        $quoter = $this->createQuoter();
        $char = $this->quoteCharacter();

        $identifier = 'col' . $char . 'name';
        $result = $quoter->quote($identifier);

        self::assertNotEmpty($result);
        self::assertStringStartsWith($char, $result);
        self::assertStringEndsWith($char, $result);

        $simpleQuoted = $char . $identifier . $char;
        self::assertGreaterThanOrEqual(
            strlen($simpleQuoted),
            strlen($result),
            'Escaped identifier should be at least as long as non-escaped form'
        );
    }

    /**
     * quote() must produce exact expected output for a simple identifier (P-IQ-2).
     */
    public function testQuoteProducesExactResult(): void
    {
        $quoter = $this->createQuoter();
        $char = $this->quoteCharacter();

        $result = $quoter->quote('users');

        self::assertSame($char . 'users' . $char, $result);
    }

    /**
     * quote() with embedded quote character must double-escape it.
     */
    public function testQuoteEscapesEmbeddedQuoteExactly(): void
    {
        $quoter = $this->createQuoter();
        $char = $this->quoteCharacter();

        $result = $quoter->quote('col' . $char . 'name');

        $expected = $char . 'col' . $char . $char . 'name' . $char;
        self::assertSame($expected, $result);
    }

    /**
     * Same input must produce identical output (P-IQ-4).
     */
    public function testQuoteIsDeterministic(): void
    {
        $quoter = $this->createQuoter();

        $result1 = $quoter->quote('my_table');
        $result2 = $quoter->quote('my_table');

        self::assertSame($result1, $result2);
    }

    /**
     * The original identifier name must be recoverable from the output (P-IQ-3).
     */
    public function testQuotedIdentifierContainsOriginalName(): void
    {
        $quoter = $this->createQuoter();

        $identifier = 'column_name';
        $result = $quoter->quote($identifier);

        self::assertStringContainsString($identifier, $result);
    }
}
