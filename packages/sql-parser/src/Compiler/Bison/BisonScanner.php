<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;

/**
 * Splits a Bison grammar file into tokens, up to the end of the rules section.
 *
 * The prologue between `%{` and `%}`, comments, and the epilogue after the
 * second `%%` are not grammar and are dropped. Host code in braces is kept as
 * a single token so that the rules reader can tell a mid-rule action from a
 * final one.
 *
 * @visibility root
 */
final class BisonScanner
{
    /**
     * @param CodeBlockReader $code Reads brace-delimited host code
     */
    public function __construct(private readonly CodeBlockReader $code = new CodeBlockReader())
    {
    }

    /**
     * Tokenizes a grammar file.
     *
     * @param string $source Contents of the file
     *
     * @return list<BisonToken> The tokens of the declarations and rules sections
     *
     * @throws GrammarSourceException When a literal or block never closes
     */
    public function scan(string $source): array
    {
        $reader = new SourceReader($source);
        $tokens = [];
        $sections = 0;
        while (!$reader->eof() && $sections < 2) {
            $token = $this->next($reader);
            if ($token === null) {
                continue;
            }
            if ($token->kind === BisonTokenKind::Section) {
                $sections++;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * Reads the token at the current position, or skips what is not one.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     *
     * @return BisonToken|null The token, or null when whitespace, a comment or the prologue was skipped
     *
     * @throws GrammarSourceException When a literal or block never closes
     */
    public function next(SourceReader $reader): ?BisonToken
    {
        $line = $reader->line();
        if ($reader->match('\s+') !== null || $this->skipComment($reader)) {
            return null;
        }
        if ($reader->startsWith('%{')) {
            if (!$reader->skipPast('%}')) {
                throw GrammarSourceException::unterminated('prologue', $line);
            }

            return null;
        }
        if ($reader->startsWith('%%')) {
            $reader->take(2);

            return new BisonToken(BisonTokenKind::Section, '%%', $line);
        }
        $directive = $reader->match('%[A-Za-z][A-Za-z0-9_.-]*');
        if ($directive !== null) {
            return new BisonToken(BisonTokenKind::Directive, substr($directive, 1), $line);
        }

        return $this->literal($reader, $line) ?? $this->word($reader, $line) ?? $this->punctuation($reader, $line);
    }

    /**
     * Consumes a comment when one starts here.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     *
     * @return bool True when a comment was skipped
     *
     * @throws GrammarSourceException When a block comment never closes
     */
    public function skipComment(SourceReader $reader): bool
    {
        if ($reader->startsWith('//')) {
            $reader->match('[^\n]*');

            return true;
        }
        if (!$reader->startsWith('/*')) {
            return false;
        }
        $line = $reader->line();
        if (!$reader->skipPast('*/')) {
            throw GrammarSourceException::unterminated('comment', $line);
        }

        return true;
    }

    /**
     * Reads a character literal, a string, a tag or a code block.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     * @param int $line Line the token starts on
     *
     * @return BisonToken|null The token, or null when none of these starts here
     *
     * @throws GrammarSourceException When the literal or block never closes
     */
    public function literal(SourceReader $reader, int $line): ?BisonToken
    {
        $character = $reader->peek();
        if ($character === '{') {
            return new BisonToken(BisonTokenKind::Code, $this->code->read($reader), $line);
        }
        if ($character === "'") {
            $literal = $reader->match("'(?:\\\\.|[^\\\\'])'");
            if ($literal === null) {
                throw GrammarSourceException::unterminated('character literal', $line);
            }

            return new BisonToken(BisonTokenKind::CharLiteral, $this->unescape(substr($literal, 1, -1)), $line);
        }
        if ($character === '"') {
            $literal = $reader->match('"(?:\\\\.|[^\\\\"\n])*"');
            if ($literal === null) {
                throw GrammarSourceException::unterminated('string', $line);
            }

            return new BisonToken(BisonTokenKind::String, $this->unescape(substr($literal, 1, -1)), $line);
        }
        if ($character === '<') {
            $tag = $reader->match('<[^>\n]*>');
            if ($tag === null) {
                throw GrammarSourceException::unterminated('type tag', $line);
            }

            return new BisonToken(BisonTokenKind::Tag, substr($tag, 1, -1), $line);
        }

        return null;
    }

    /**
     * Reads an identifier or a number.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     * @param int $line Line the token starts on
     *
     * @return BisonToken|null The token, or null when neither starts here
     */
    public function word(SourceReader $reader, int $line): ?BisonToken
    {
        $identifier = $reader->match('[A-Za-z_.$][A-Za-z0-9_.$]*');
        if ($identifier !== null) {
            return new BisonToken(BisonTokenKind::Identifier, $identifier, $line);
        }
        $number = $reader->match('[0-9]+');
        if ($number !== null) {
            return new BisonToken(BisonTokenKind::Number, $number, $line);
        }

        return null;
    }

    /**
     * Reads one punctuation byte.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     * @param int $line Line the token starts on
     *
     * @return BisonToken The token
     */
    public function punctuation(SourceReader $reader, int $line): BisonToken
    {
        $character = $reader->take(1);
        $kind = match ($character) {
            ':' => BisonTokenKind::Colon,
            '|' => BisonTokenKind::Pipe,
            ';' => BisonTokenKind::Semicolon,
            '[' => BisonTokenKind::BracketOpen,
            ']' => BisonTokenKind::BracketClose,
            default => BisonTokenKind::Other,
        };

        return new BisonToken($kind, $character, $line);
    }

    /**
     * Resolves the escape sequences a Bison literal may contain.
     *
     * @param string $text Literal body as written
     *
     * @return string The characters it stands for
     */
    public function unescape(string $text): string
    {
        return strtr($text, [
            "\\'" => "'",
            '\\"' => '"',
            '\\\\' => '\\',
            '\\n' => "\n",
            '\\t' => "\t",
            '\\r' => "\r",
            '\\0' => "\0",
        ]);
    }
}
