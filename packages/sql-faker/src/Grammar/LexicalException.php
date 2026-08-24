<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Reports that concrete SQL did not preserve the derived parser-token sequence.
 *
 * Tokenizing is applied to SQL text, so a mismatch describes the input rather
 * than a defect in the lexer, and callers are expected to handle it.
 *
 * Each dialect hits the same failures with its own name attached, so the
 * wording lives here rather than being spelled out three times. What the
 * generator writes and what the tokenizer reads back are the two halves of one
 * contract, and every way they can disagree is named below.
 */
final class LexicalException extends RuntimeException
{
    /**
     * Reports a terminal the dialect's profile does not describe.
     *
     * @param string $dialect Dialect name as it appears in messages, e.g. "MySQL"
     * @param string $version Lexical profile version in use
     * @param string $terminal Terminal that was asked for
     *
     * @return self Exception naming the terminal and the profile
     */
    public static function unsupportedTerminal(string $dialect, string $version, string $terminal): self
    {
        return new self("Unsupported {$dialect} terminal for {$version}: {$terminal}");
    }

    /**
     * Reports SQL text the tokenizer cannot read at all.
     *
     * @param string $dialect Dialect name as it appears in messages
     * @param int $offset Where the tokenizer stopped
     * @param string $sql The text being read
     *
     * @return self Exception naming the offset and the text
     */
    public static function unsupportedInput(string $dialect, int $offset, string $sql): self
    {
        return new self("Unsupported {$dialect} lexical input at offset {$offset}: {$sql}");
    }

    /**
     * Reports a quoted run that never closes.
     *
     * @param string $dialect Dialect name as it appears in messages
     * @param string $sql The text being read
     *
     * @return self Exception naming the text
     */
    public static function unterminatedQuotedToken(string $dialect, string $sql): self
    {
        return new self("Unterminated {$dialect} quoted token: {$sql}");
    }

    /**
     * Reports a bracketed identifier that never closes.
     *
     * @param string $dialect Dialect name as it appears in messages
     *
     * @return self Exception describing the truncation
     */
    public static function unterminatedBracketIdentifier(string $dialect): self
    {
        return new self("Unterminated {$dialect} bracket identifier.");
    }

    /**
     * Reports a block comment that never closes.
     *
     * @param string $dialect Dialect name as it appears in messages
     *
     * @return self Exception describing the truncation
     */
    public static function unterminatedBlockComment(string $dialect): self
    {
        return new self("Unterminated {$dialect} block comment.");
    }

    /**
     * Reports a dollar-quoted string that never closes.
     *
     * @param string $dialect Dialect name as it appears in messages
     *
     * @return self Exception describing the truncation
     */
    public static function unterminatedDollarQuotedString(string $dialect): self
    {
        return new self("Unterminated {$dialect} dollar-quoted string.");
    }

    /**
     * Reports a caller-supplied lexeme that does not tokenize to the terminal it was given for.
     *
     * @param string $dialect Dialect name as it appears in messages
     * @param string $terminal Terminal the lexeme was meant to realize
     * @param string $lexeme The text the caller asked for
     *
     * @return self Exception naming the terminal and the text
     */
    public static function lexemeDoesNotRealizeTerminal(string $dialect, string $terminal, string $lexeme): self
    {
        return new self("Requested {$dialect} lexeme does not realize {$terminal}: {$lexeme}");
    }

    /**
     * Reports a caller-supplied lexeme the catalog has no witness for.
     *
     * @param string $dialect Dialect name as it appears in messages
     * @param string $terminal Terminal the lexeme was meant to realize
     * @param string $lexeme The text the caller asked for
     *
     * @return self Exception naming the terminal and the text
     */
    public static function noWitnessForLexeme(string $dialect, string $terminal, string $lexeme): self
    {
        return new self("{$dialect} lexical catalog has no {$terminal} witness for: {$lexeme}");
    }

    /**
     * Reports SQL that did not tokenize back to the terminals it was generated from.
     *
     * This is the failure the whole realization path exists to catch: the
     * generator believed it was writing one token sequence and the dialect's own
     * rules read another, so both sequences and the text are reported together.
     *
     * @param string $dialect Dialect name as it appears in messages
     * @param string $version Lexical profile version in use
     * @param list<string> $expected Tokens the terminals were meant to produce
     * @param list<string> $actual Tokens the text actually produced
     * @param string $sql The generated text
     *
     * @return self Exception carrying both sequences and the text
     */
    public static function roundTripMismatch(
        string $dialect,
        string $version,
        array $expected,
        array $actual,
        string $sql,
    ): self {
        return new self(sprintf(
            "%s lexical round-trip failed for %s.\nExpected: %s\nActual: %s\nSQL: %s",
            $dialect,
            $version,
            self::rendered($expected),
            self::rendered($actual),
            $sql,
        ));
    }

    /**
     * Renders a token sequence for a failure message.
     *
     * The sequences being compared came out of arbitrary generated bytes, so
     * rendering them can meet input JSON has no encoding for. Substituting
     * those bytes keeps the round-trip failure reportable: a message that could
     * itself fail would hide the thing worth reporting behind an encoding
     * error.
     *
     * @param list<string> $tokens Tokens to render
     *
     * @return string The sequence as JSON, or a placeholder when it cannot be rendered
     */
    public static function rendered(array $tokens): string
    {
        $json = json_encode($tokens, JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($json) ? $json : '(unrenderable)';
    }
}
