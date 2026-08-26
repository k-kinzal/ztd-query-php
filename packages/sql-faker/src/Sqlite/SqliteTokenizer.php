<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Grammar\LexicalException;

/**
 * Reads SQLite text back into the parser tokens the server would produce.
 *
 * This is the inverse of realization and the reason realization can be trusted:
 * SQL written from a terminal sequence is read back here, and the two sequences
 * must agree. SQLite's rules are its own — an identifier may be quoted four
 * ways, a digit-separated number is a token of its own, and a parameter may be
 * written with any of five sigils — so a tokenizer that merely looked plausible
 * would agree with a generator that was equally wrong.
 */
final class SqliteTokenizer
{
    private const OPERATORS = [
        '->>' => 'PTR',
        '->' => 'PTR',
        '||' => 'CONCAT',
        '==' => 'EQ',
        '<=' => 'LE',
        '<>' => 'NE',
        '!=' => 'NE',
        '>=' => 'GE',
        '<<' => 'LSHIFT',
        '>>' => 'RSHIFT',
    ];

    private const PUNCTUATION = [
        '(' => 'LP',
        ')' => 'RP',
        ';' => 'SEMI',
        ',' => 'COMMA',
        '.' => 'DOT',
        '=' => 'EQ',
        '<' => 'LT',
        '>' => 'GT',
        '+' => 'PLUS',
        '-' => 'MINUS',
        '*' => 'STAR',
        '/' => 'SLASH',
        '%' => 'REM',
        '&' => 'BITAND',
        '|' => 'BITOR',
        '~' => 'BITNOT',
    ];

    /**
     * @param array<string, string> $keywordTokens Terminal name by upper-cased keyword
     */
    public function __construct(private readonly array $keywordTokens)
    {
    }

    /**
     * Reads SQL text into the tokens SQLite's own lexer would produce.
     *
     * @param string $sql Text to read
     *
     * @return list<string> Parser token names, in order
     *
     * @throws LexicalException When the text holds something the lexer cannot read
     */
    public function tokenize(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            $startedAt = $offset;
            if ($this->skipTrivia($sql, $offset)) {
                if ($offset <= $startedAt) {
                    throw LexicalException::noProgress('SQLite', $startedAt, $sql);
                }

                continue;
            }

            $token = $this->tokenAt($sql, $offset);
            if ($token === null) {
                throw LexicalException::unsupportedInput('SQLite', $offset, $sql);
            }

            if ($offset <= $startedAt) {
                throw LexicalException::noProgress('SQLite', $startedAt, $sql);
            }

            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * Reads the one token that starts at the offset, advancing past it.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when nothing here is a token
     *
     * @throws LexicalException When a quoted or bracketed run never closes
     */
    public function tokenAt(string $sql, int &$offset): ?string
    {
        return $this->quotedTokenAt($sql, $offset)
            ?? $this->literalTokenAt($sql, $offset)
            ?? $this->wordTokenAt($sql, $offset)
            ?? $this->operatorTokenAt($sql, $offset);
    }

    /**
     * Reads a quoted string or a quoted identifier in any of SQLite's spellings.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when no quoted run opens here
     *
     * @throws LexicalException When the run never closes
     */
    public function quotedTokenAt(string $sql, int &$offset): ?string
    {
        $character = $sql[$offset];

        if ($character === "'") {
            $this->skipQuoted($sql, $offset, "'");

            return 'STRING';
        }

        if ($character === '"' || $character === '`') {
            $this->skipQuoted($sql, $offset, $character);

            return 'ID';
        }

        if ($character !== '[') {
            return null;
        }

        $end = strpos($sql, ']', $offset + 1);
        if ($end === false) {
            throw LexicalException::unterminatedBracketIdentifier('SQLite');
        }
        $offset = $end + 1;

        return 'ID';
    }

    /**
     * Reads a blob, a bound parameter, or a numeric literal.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when none of these start here
     */
    public function literalTokenAt(string $sql, int &$offset): ?string
    {
        $patterns = [
            "/\G[Xx]'(?:[0-9A-Fa-f]{2})*'/" => 'BLOB',
            '/\G(?:\?[0-9]*|[:@$#][A-Za-z_][A-Za-z0-9_]*)/' => 'VARIABLE',
            '/\G(?:(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\d+[eE][+-]?\d+)/' => 'FLOAT',
        ];

        foreach ($patterns as $pattern => $token) {
            if (preg_match($pattern, $sql, $match, 0, $offset) !== 1) {
                continue;
            }

            $offset += strlen($match[0]);

            return $token;
        }

        if (preg_match('/\G(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|\d(?:_?\d)*)/', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return str_contains($match[0], '_') ? 'QNUMBER' : 'INTEGER';
    }

    /**
     * Reads a keyword or an unquoted identifier.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when no word starts here
     */
    public function wordTokenAt(string $sql, int &$offset): ?string
    {
        if (preg_match('/\G[A-Za-z_][A-Za-z0-9_$]*/', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return $this->keywordTokens[strtoupper($match[0])] ?? 'ID';
    }

    /**
     * Reads an operator or a single punctuation character.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when nothing operator-like starts here
     */
    public function operatorTokenAt(string $sql, int &$offset): ?string
    {
        $operator = $this->operatorAt($sql, $offset);
        if ($operator === null) {
            return null;
        }

        $offset += strlen($operator[0]);

        return $operator[1];
    }

    /**
     * Consumes whitespace or one comment.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return bool True when something was skipped
     *
     * @throws LexicalException When a block comment never closes
     */
    public function skipTrivia(string $sql, int &$offset): bool
    {
        if (preg_match('/\G\s+/A', $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return true;
        }

        if (substr($sql, $offset, 2) === '--') {
            $end = strpos($sql, "\n", $offset + 2);
            $offset = $end === false ? strlen($sql) : $end + 1;

            return true;
        }

        if (substr($sql, $offset, 2) !== '/*') {
            return false;
        }

        $end = strpos($sql, '*/', $offset + 2);
        if ($end === false) {
            throw LexicalException::unterminatedBlockComment('SQLite');
        }
        $offset = $end + 2;

        return true;
    }

    /**
     * Consumes a quoted run, doubling being the way a quote escapes itself.
     *
     * @param string $sql Text being read
     * @param int $offset Offset of the opening quote; moved past the closing one
     * @param string $quote The quote character
     *
     * @throws LexicalException When the run never closes
     */
    public function skipQuoted(string $sql, int &$offset, string $quote): void
    {
        $length = strlen($sql);
        ++$offset;

        while ($offset < $length) {
            if ($sql[$offset] !== $quote) {
                ++$offset;
                continue;
            }
            if (($sql[$offset + 1] ?? null) === $quote) {
                $offset += 2;
                continue;
            }
            ++$offset;

            return;
        }

        throw LexicalException::unterminatedQuotedToken('SQLite', $sql);
    }

    /**
     * Reports the operator written at the offset and the token it stands for.
     *
     * @param string $sql Text being read
     * @param int $offset Where to look
     *
     * @return array{string, string}|null The operator and its token, or null when there is none
     */
    public function operatorAt(string $sql, int $offset): ?array
    {
        foreach (self::OPERATORS as $operator => $token) {
            if (str_starts_with(substr($sql, $offset), $operator)) {
                return [$operator, $token];
            }
        }

        $token = self::PUNCTUATION[$sql[$offset]] ?? null;

        return $token !== null ? [$sql[$offset], $token] : null;
    }

    /**
     * Strips the `TK_` prefix and the spacing tokens SQLite's own lexer emits.
     *
     * Witnesses record what SQLite's lexer produced, which names tokens with a
     * prefix and reports whitespace as a token of its own. Neither belongs in
     * the parser-token sequence this tokenizer is compared against.
     *
     * @param list<string> $tokens Tokens as the upstream lexer named them
     *
     * @return list<string> The same tokens as the parser names them
     */
    public function normalizedSourceTokens(array $tokens): array
    {
        $normalized = [];
        foreach ($tokens as $token) {
            if ($token === 'TK_SPACE') {
                continue;
            }
            $normalized[] = str_starts_with($token, 'TK_') ? substr($token, 3) : $token;
        }

        return $normalized;
    }
}
