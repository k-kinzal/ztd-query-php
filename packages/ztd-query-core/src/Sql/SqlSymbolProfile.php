<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

use ZtdQuery\Exception\InvalidDefinitionException;

/**
 * How one dialect spells what is written plainly.
 *
 * Which characters may spell an identifier, how far a number runs, what
 * nests, what brackets, and what ends a statement or separates a list are
 * all asked here, because they are the shapes a scanner tells apart without
 * knowing what any of them mean.
 */
final class SqlSymbolProfile
{
    /** @var array{non-empty-string, non-empty-string}|null */
    private readonly ?array $bracketPair;

    /** @var array{non-empty-string, non-empty-string} */
    private readonly array $nestingPair;

    /**
     * @param string $numericLiteralPattern Pattern a number is written as
     * @param string $identifierStartPattern Pattern the first character of an identifier matches
     * @param string $identifierPartPattern Pattern every later character of an identifier matches
     * @param array{string, string}|null $bracketPair Opening and closing bracket, or null where the dialect has none
     * @param array{string, string} $nestingPair Opening and closing delimiter that nest
     * @param string $statementDelimiter Single character that ends a statement
     * @param string $listDelimiter Single character that separates list items
     * @param LexicalPattern $patterns Reads a regular expression against a position
     *
     * @throws InvalidDefinitionException When a pattern is unreadable, a delimiter is empty, or a single-character delimiter is not one character
     */
    public function __construct(
        private readonly string $numericLiteralPattern,
        private readonly string $identifierStartPattern,
        private readonly string $identifierPartPattern,
        ?array $bracketPair,
        array $nestingPair,
        private readonly string $statementDelimiter,
        private readonly string $listDelimiter,
        private readonly LexicalPattern $patterns = new LexicalPattern(),
    ) {
        $this->patterns->assertValid($this->numericLiteralPattern);
        $this->patterns->assertValid($this->identifierStartPattern);
        $this->patterns->assertValid($this->identifierPartPattern);
        if ($bracketPair !== null && ($bracketPair[0] === '' || $bracketPair[1] === '')) {
            throw new InvalidDefinitionException('Bracket delimiters must not be empty.');
        }
        /** @var array{non-empty-string, non-empty-string}|null $bracketPair */
        $this->bracketPair = $bracketPair;
        if ($nestingPair[0] === '' || $nestingPair[1] === '') {
            throw new InvalidDefinitionException('Nesting delimiters must not be empty.');
        }
        /** @var array{non-empty-string, non-empty-string} $nestingPair */
        $this->nestingPair = $nestingPair;
        if (strlen($this->statementDelimiter) !== 1 || strlen($this->listDelimiter) !== 1) {
            throw new InvalidDefinitionException('Statement and list delimiters must be single characters.');
        }
    }

    /**
     * Answers how long the number starting here is.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int Its length, or zero when no number starts there
     */
    public function numberLengthAt(string $sql, int $offset): int
    {
        $match = $this->patterns->matchAt($this->numericLiteralPattern, $sql, $offset);

        return $match === null ? 0 : strlen($match);
    }

    /**
     * Reports whether an identifier may begin with this character.
     *
     * @param string $character Character to test
     *
     * @return bool True when it may
     */
    public function isIdentifierStart(string $character): bool
    {
        return $this->patterns->matchesCharacter($this->identifierStartPattern, $character);
    }

    /**
     * Reports whether an identifier may continue with this character.
     *
     * @param string $character Character to test
     *
     * @return bool True when it may
     */
    public function isIdentifierPart(string $character): bool
    {
        return $this->patterns->matchesCharacter($this->identifierPartPattern, $character);
    }

    /**
     * Reports whether this character opens a bracket.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does, and false where the dialect brackets nothing
     */
    public function isBracketOpening(string $character): bool
    {
        return $this->bracketPair !== null && $character === $this->bracketPair[0];
    }

    /**
     * Reports whether this character closes a bracket.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does, and false where the dialect brackets nothing
     */
    public function isBracketClosing(string $character): bool
    {
        return $this->bracketPair !== null && $character === $this->bracketPair[1];
    }

    /**
     * Reports whether this character opens a nesting.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does
     */
    public function isNestingOpening(string $character): bool
    {
        return $character === $this->nestingPair[0];
    }

    /**
     * Reports whether this character closes a nesting.
     *
     * @param string $character Character to test
     *
     * @return bool True when it does
     */
    public function isNestingClosing(string $character): bool
    {
        return $character === $this->nestingPair[1];
    }

    /**
     * Reports whether this symbol ends a statement.
     *
     * @param string $symbol Symbol to test
     *
     * @return bool True when it does
     */
    public function isStatementDelimiter(string $symbol): bool
    {
        return $symbol === $this->statementDelimiter;
    }

    /**
     * Answers the character that separates list items.
     *
     * @return string The separator
     */
    public function listDelimiter(): string
    {
        return $this->listDelimiter;
    }
}
