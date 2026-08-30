<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Source\Bison\Lexer;

use InvalidArgumentException;
use SqlFaker\Grammar\Source\GrammarParseException;

/**
 * Adds lookahead to a Bison lexer.
 *
 * A recursive-descent parser has to decide what it is reading before it commits
 * to reading it, which means looking at tokens it does not want to consume yet.
 * Buffering those here keeps the lexer a plain forward scan: nothing in it has
 * to know that a token may be read twice.
 */
final class BisonTokenStream
{
    /** @readonly */
    private BisonLexer $lexer;

    /**
     * Tokens already scanned but not yet consumed, in reading order.
     *
     * @var list<BisonToken>
     */
    private array $lookahead = [];

    /**
     * @param BisonLexer $lexer Source of tokens
     */
    public function __construct(BisonLexer $lexer)
    {
        $this->lexer = $lexer;
    }

    /**
     * Builds a stream over grammar source text.
     *
     * @param string $source Bison grammar text
     *
     * @return self A stream reading that source with the default lexeme rules
     */
    public static function over(string $source): self
    {
        return new self(new BisonLexer($source));
    }

    /**
     * Consumes the next token.
     *
     * @return BisonToken The token, taken from the lookahead buffer when one is waiting
     *
     * @throws GrammarParseException When the source cannot be tokenized
     */
    public function next(): BisonToken
    {
        $buffered = array_shift($this->lookahead);

        return $buffered ?? $this->lexer->scan();
    }

    /**
     * Reads the next token without consuming it.
     *
     * @return BisonToken The token that the next call to next() will return
     *
     * @throws GrammarParseException When the source cannot be tokenized
     */
    public function peek(): BisonToken
    {
        return $this->peekN(1);
    }

    /**
     * Reads a token further ahead without consuming anything.
     *
     * @param int $distance How many tokens ahead to look, counting from one
     *
     * @return BisonToken The token at that distance
     *
     * @throws InvalidArgumentException When the distance is less than one
     * @throws GrammarParseException When the source cannot be tokenized
     */
    public function peekN(int $distance): BisonToken
    {
        if ($distance < 1) {
            throw new InvalidArgumentException('peekN($n) requires $n >= 1');
        }

        while (count($this->lookahead) < $distance) {
            $this->lookahead[] = $this->lexer->scan();
        }

        return $this->lookahead[$distance - 1];
    }

    /**
     * Consumes the next token only when it is one of the given kinds.
     *
     * Reading a grammar is mostly "take this if it is there": a type tag, an
     * explicit token code, an alias. Asking the stream keeps that one step, so
     * a reader cannot look at one token and then consume another.
     *
     * @param BisonLexeme ...$accepted Kinds the caller is willing to take
     *
     * @return BisonToken|null The consumed token, or null with the stream unmoved
     *
     * @throws GrammarParseException When the source cannot be tokenized
     */
    public function nextIf(BisonLexeme ...$accepted): ?BisonToken
    {
        if (!in_array($this->peek()->type, $accepted, true)) {
            return null;
        }

        return $this->next();
    }

    /**
     * Consumes the next token and reads its value as a string.
     *
     * @return string The token value, with a numeric value rendered as digits
     *
     * @throws GrammarParseException When the source cannot be tokenized
     */
    public function nextString(): string
    {
        return $this->next()->asString();
    }

    /**
     * Consumes the next token and reads its value as an integer.
     *
     * @return int The token value, with a non-numeric value read as zero
     *
     * @throws GrammarParseException When the source cannot be tokenized
     */
    public function nextInt(): int
    {
        return $this->next()->asInt();
    }

    /**
     * Consumes the rest of the source as raw text.
     *
     * Buffered lookahead is discarded rather than prepended: the cursor has
     * already moved past those tokens, so the text they came from is behind the
     * point the epilogue starts at.
     *
     * @return string Everything left after the tokens already scanned
     */
    public function consumeRemaining(): string
    {
        $this->lookahead = [];

        return $this->lexer->consumeRemaining();
    }
}
