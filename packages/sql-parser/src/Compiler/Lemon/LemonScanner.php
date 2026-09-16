<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\CodeBlockReader;
use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Compiler\SourceReader;

/**
 * Splits a Lemon grammar file into tokens.
 *
 * Comments are dropped and host code in braces is kept as one token. An
 * alias in parentheses and a precedence mark in brackets are tokens of their
 * own, as they attach to the symbol or rule before them.
 *
 * @visibility root
 */
final class LemonScanner
{
    /**
     * @param CodeBlockReader $code Reads brace-delimited host code
     */
    public function __construct(private readonly CodeBlockReader $code = new CodeBlockReader())
    {
    }

    /**
     * Tokenizes a grammar file whose conditionals have been applied.
     *
     * @param string $source Contents of the file
     *
     * @return list<LemonToken> The tokens
     *
     * @throws GrammarSourceException When a literal or block never closes
     */
    public function scan(string $source): array
    {
        $reader = new SourceReader($source);
        $tokens = [];
        while (!$reader->eof()) {
            $token = $this->next($reader);
            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Reads the token at the current position, or skips what is not one.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     *
     * @return LemonToken|null The token, or null when whitespace or a comment was skipped
     *
     * @throws GrammarSourceException When a literal or block never closes
     */
    public function next(SourceReader $reader): ?LemonToken
    {
        $line = $reader->line();
        if ($reader->match('\s+') !== null || $reader->match('//[^\n]*') !== null) {
            return null;
        }
        if ($reader->startsWith('/*')) {
            if (!$reader->skipPast('*/')) {
                throw GrammarSourceException::unterminated('comment', $line);
            }

            return null;
        }

        return $this->bracketed($reader, $line) ?? $this->word($reader, $line) ?? $this->punctuation($reader, $line);
    }

    /**
     * Reads a code block, an alias, a precedence mark or a string.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     * @param int $line Line the token starts on
     *
     * @return LemonToken|null The token, or null when none of these starts here
     *
     * @throws GrammarSourceException When the block or literal never closes
     */
    public function bracketed(SourceReader $reader, int $line): ?LemonToken
    {
        $character = $reader->peek();
        if ($character === '{') {
            return new LemonToken(LemonTokenKind::Code, $this->code->read($reader), $line);
        }
        foreach (['(' => [')', LemonTokenKind::Alias], '[' => [']', LemonTokenKind::PrecedenceMark]] as $open => [$close, $kind]) {
            if ($character !== $open) {
                continue;
            }
            $text = $reader->match(preg_quote($open, '/') . '\s*[A-Za-z_][A-Za-z0-9_]*\s*' . preg_quote($close, '/'));
            if ($text === null) {
                throw GrammarSourceException::unexpected("a name in {$open}{$close}", "'{$character}'", $line);
            }

            return new LemonToken($kind, trim(substr($text, 1, -1)), $line);
        }
        if ($character === '"') {
            $text = $reader->match('"(?:\\\\.|[^\\\\"\n])*"');
            if ($text === null) {
                throw GrammarSourceException::unterminated('string', $line);
            }

            return new LemonToken(LemonTokenKind::String, substr($text, 1, -1), $line);
        }

        return null;
    }

    /**
     * Reads a directive, an identifier or a number.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     * @param int $line Line the token starts on
     *
     * @return LemonToken|null The token, or null when none of these starts here
     */
    public function word(SourceReader $reader, int $line): ?LemonToken
    {
        $directive = $reader->match('%[A-Za-z_]+');
        if ($directive !== null) {
            return new LemonToken(LemonTokenKind::Directive, substr($directive, 1), $line);
        }
        $identifier = $reader->match('[A-Za-z_][A-Za-z0-9_]*');
        if ($identifier !== null) {
            return new LemonToken(LemonTokenKind::Identifier, $identifier, $line);
        }
        $number = $reader->match('[0-9]+');
        if ($number !== null) {
            return new LemonToken(LemonTokenKind::Number, $number, $line);
        }

        return null;
    }

    /**
     * Reads the rule arrow or one punctuation byte.
     *
     * @param SourceReader $reader Reader positioned on the next byte to read
     * @param int $line Line the token starts on
     *
     * @return LemonToken The token
     */
    public function punctuation(SourceReader $reader, int $line): LemonToken
    {
        if ($reader->startsWith('::=')) {
            return new LemonToken(LemonTokenKind::Arrow, $reader->take(3), $line);
        }
        $character = $reader->take(1);
        $kind = match ($character) {
            '.' => LemonTokenKind::Dot,
            '|' => LemonTokenKind::Pipe,
            default => LemonTokenKind::Other,
        };

        return new LemonToken($kind, $character, $line);
    }
}
