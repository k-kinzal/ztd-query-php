<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * Everything the neutral scanner needs to know about one dialect's spelling.
 *
 * The scanner itself knows no dialect: which characters open a comment, how an
 * identifier is quoted, what a parameter looks like, whether a backslash
 * escapes inside a string — all of it is answered from here. A database
 * package builds one of these, and it is checked as it is built rather than
 * relied on to be usable while scanning.
 *
 * What a dialect spells falls into four kinds, and each is a profile of its
 * own that says whether the data it was given could be scanned with. This is
 * the one thing the scanner and every rewriter ask, so that neither has to
 * know which of the four an answer came from.
 */
final class SqlLexerProfile
{
    /**
     * @param SqlCommentProfile $comments How the dialect writes a comment
     * @param SqlQuoteProfile $quotes How it closes a run of text a quote opened
     * @param SqlParameterProfile $parameters How it writes a placeholder
     * @param SqlSymbolProfile $symbols How it spells what is written plainly
     */
    public function __construct(
        private readonly SqlCommentProfile $comments,
        private readonly SqlQuoteProfile $quotes,
        private readonly SqlParameterProfile $parameters,
        private readonly SqlSymbolProfile $symbols,
    ) {
    }

    /**
     * Reports whether a comment running to the end of the line starts here.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return bool True when one starts there
     */
    public function startsLineComment(string $sql, int $offset): bool
    {
        return $this->comments->startsLineComment($sql, $offset);
    }

    /**
     * Answers the block comment delimiters starting here, if any.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return array{non-empty-string, non-empty-string}|null The opening and closing delimiters, or null when no comment starts there
     */
    public function blockCommentAt(string $sql, int $offset): ?array
    {
        return $this->comments->blockCommentAt($sql, $offset);
    }

    /**
     * Reports whether a block comment may contain another.
     *
     * @return bool True when the dialect nests them
     */
    public function supportsNestedBlockComments(): bool
    {
        return $this->comments->supportsNestedBlockComments();
    }

    /**
     * Answers the quote that closes a string this one opened.
     *
     * @param string $opening Quote that opened it
     *
     * @return string|null The closing quote, or null when nothing opens a string with that
     */
    public function stringQuoteClosing(string $opening): ?string
    {
        return $this->quotes->stringQuoteClosing($opening);
    }

    /**
     * Answers the quote that closes an identifier this one opened.
     *
     * @param string $opening Quote that opened it
     *
     * @return string|null The closing quote, or null when nothing opens an identifier with that
     */
    public function identifierQuoteClosing(string $opening): ?string
    {
        return $this->quotes->identifierQuoteClosing($opening);
    }

    /**
     * Answers the name a quoted identifier stands for.
     *
     * @param string $identifier Identifier as it was written
     *
     * @return string The name, or the identifier unchanged when it was not quoted
     */
    public function unquoteIdentifier(string $identifier): string
    {
        return $this->quotes->unquoteIdentifier($identifier);
    }

    /**
     * Answers the name a quoted identifier stands for, and nothing for anything else.
     *
     * @param string $identifier Identifier as it was written
     *
     * @return string|null The name, or null when it was not a complete quoted identifier
     */
    public function quotedIdentifierValue(string $identifier): ?string
    {
        return $this->quotes->quotedIdentifierValue($identifier);
    }

    /**
     * Answers the dollar-quoted delimiter starting here, if any.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The delimiter, or null when none starts there
     */
    public function dollarQuoteDelimiterAt(string $sql, int $offset): ?string
    {
        return $this->quotes->dollarQuoteDelimiterAt($sql, $offset);
    }

    /**
     * Reports whether the string opening here treats a backslash as an escape.
     *
     * @param string $sql Statement being scanned
     * @param int $quoteOffset Position of the quote that opens the string
     *
     * @return bool True when a backslash escapes inside it
     */
    public function stringUsesBackslashEscapes(string $sql, int $quoteOffset): bool
    {
        return $this->quotes->stringUsesBackslashEscapes($sql, $quoteOffset, $this->symbols);
    }

    /**
     * Answers how long the positional parameter starting here is.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int Its length, or zero when none starts there
     */
    public function positionalParameterLengthAt(string $sql, int $offset): int
    {
        return $this->parameters->positionalParameterLengthAt($sql, $offset);
    }

    /**
     * Answers the prefix of the named parameter starting here, if any.
     *
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The prefix, or null when no parameter starts there
     */
    public function namedParameterPrefixAt(string $sql, int $offset): ?string
    {
        return $this->parameters->namedParameterPrefixAt($sql, $offset);
    }

    /**
     * Answers what separates a parameter prefix from its name here, if anything.
     *
     * @param string $prefix Prefix the parameter was written with
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return string|null The separator, or null when none is written there
     */
    public function parameterNameSeparatorAt(string $prefix, string $sql, int $offset): ?string
    {
        return $this->parameters->parameterNameSeparatorAt($prefix, $sql, $offset);
    }

    /**
     * Answers how much a parameter written with this prefix carries after its name.
     *
     * @param string $prefix Prefix the parameter was written with
     * @param string $sql Statement being scanned
     * @param int $offset Position to look at
     *
     * @return int How long it is, or zero when nothing follows
     */
    public function parameterSuffixLength(string $prefix, string $sql, int $offset): int
    {
        return $this->parameters->parameterSuffixLength($prefix, $sql, $offset);
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
        return $this->symbols->numberLengthAt($sql, $offset);
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
        return $this->symbols->isIdentifierStart($character);
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
        return $this->symbols->isIdentifierPart($character);
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
        return $this->symbols->isBracketOpening($character);
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
        return $this->symbols->isBracketClosing($character);
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
        return $this->symbols->isNestingOpening($character);
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
        return $this->symbols->isNestingClosing($character);
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
        return $this->symbols->isStatementDelimiter($symbol);
    }

    /**
     * Answers the character that separates list items.
     *
     * @return string The separator
     */
    public function listDelimiter(): string
    {
        return $this->symbols->listDelimiter();
    }
}
