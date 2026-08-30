<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Lexical;

use SqlFaker\Grammar\Lexical\LexicalException;

/**
 * Reads MySQL text back into the parser tokens the server would produce.
 *
 * This is the inverse of realization and the reason realization can be trusted:
 * SQL written from a terminal sequence is read back here, and the two sequences
 * must agree. It follows the server's own rules — a word before an open
 * parenthesis may be a function name, a run of digits widens from NUM through
 * LONG_NUM by magnitude, `WITH ROLLUP` is one token — because a tokenizer that
 * merely looked plausible would agree with a generator that was equally wrong.
 */
final class MySqlTokenizer
{
    private const OPERATORS = ['<=>', '->>', '&&', '<=', '<>', '!=', '>=', '<<', '>>', ':=', '->', '||'];

    private const PUNCTUATION = '!%&()*+,-./:;@^{}|~=<>';

    /**
     * @param array<string, string> $symbolTokens Terminal name by upper-cased keyword or operator
     * @param array<string, string> $functionTokens Terminal name by upper-cased function name
     * @param bool $dollarQuotedStrings Whether the server build accepts `$$ ... $$` strings
     */
    public function __construct(
        private readonly array $symbolTokens,
        private readonly array $functionTokens,
        private readonly bool $dollarQuotedStrings,
        private readonly MySqlTrivia $trivia = new MySqlTrivia(),
    ) {
    }

    /**
     * Reads SQL text into the tokens MySQL's own lexer would produce.
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
            if ($this->trivia->skipTrivia($sql, $offset)) {
                if ($offset <= $startedAt) {
                    throw LexicalException::noProgress('MySQL', $startedAt, $sql);
                }

                continue;
            }

            $token = $this->tokenAt($sql, $offset, $tokens);
            if ($token === null) {
                throw LexicalException::unsupportedInput('MySQL', $offset, $sql);
            }

            if ($offset <= $startedAt) {
                throw LexicalException::noProgress('MySQL', $startedAt, $sql);
            }

            $tokens[] = $token;
        }

        return $this->merged($tokens);
    }

    /**
     * Reads the one token that starts at the offset, advancing past it.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     * @param list<string> $emitted Tokens read so far, for the rules that depend on them
     *
     * @return string|null The token name, or null when nothing here is a token
     *
     * @throws LexicalException When a quoted or dollar-quoted run never closes
     */
    public function tokenAt(string $sql, int &$offset, array $emitted): ?string
    {
        return $this->quotedTokenAt($sql, $offset)
            ?? $this->markerTokenAt($sql, $offset, $emitted)
            ?? $this->numericTokenAt($sql, $offset)
            ?? $this->wordTokenAt($sql, $offset)
            ?? $this->operatorTokenAt($sql, $offset);
    }

    /**
     * Reads a backtick, single-quoted or dollar-quoted run.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when no quote opens here
     *
     * @throws LexicalException When the run never closes
     */
    public function quotedTokenAt(string $sql, int &$offset): ?string
    {
        $character = $sql[$offset];

        if ($character === '`') {
            $this->trivia->skipQuoted($sql, $offset, '`');

            return 'IDENT_QUOTED';
        }

        if ($character === "'") {
            $this->trivia->skipQuoted($sql, $offset, "'");

            return 'TEXT_STRING';
        }

        if ($character !== '$' || !$this->dollarQuotedStrings || substr($sql, $offset, 2) !== '$$') {
            return null;
        }

        $end = strpos($sql, '$$', $offset + 2);
        if ($end === false) {
            throw LexicalException::unterminatedDollarQuotedString('MySQL');
        }
        $offset = $end + 2;

        return 'DOLLAR_QUOTED_STRING_SYM';
    }

    /**
     * Reads a parameter marker, a user-variable sigil, or the hostname after one.
     *
     * A hostname is only a hostname because an `@` came before it; the same
     * characters anywhere else are an ordinary identifier, which is why the
     * tokens read so far decide this.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     * @param list<string> $emitted Tokens read so far
     *
     * @return string|null The token name, or null when none of these start here
     */
    public function markerTokenAt(string $sql, int &$offset, array $emitted): ?string
    {
        if ($sql[$offset] === '?') {
            ++$offset;

            return 'PARAM_MARKER';
        }

        if ($sql[$offset] === '@') {
            ++$offset;

            return '@';
        }

        if (($emitted[count($emitted) - 1] ?? null) !== '@'
            || preg_match('/\G[A-Za-z0-9_.%-]+/A', $sql, $match, 0, $offset) !== 1
        ) {
            return null;
        }

        $offset += strlen($match[0]);

        return 'LEX_HOSTNAME';
    }

    /**
     * Reads a numeric literal in any of the spellings MySQL accepts.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when no number starts here
     *
     * @throws LexicalException When a prefixed quoted literal never closes
     */
    public function numericTokenAt(string $sql, int &$offset): ?string
    {
        if (preg_match('/\G(?:0[xX][0-9A-Fa-f]+|0[bB][01]+)/A', $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return strtolower(substr($match[0], 0, 2)) === '0x' ? 'HEX_NUM' : 'BIN_NUM';
        }

        if (preg_match('/\G(?:[nN]|[xX]|[bB])\'/A', $sql, $match, 0, $offset) === 1) {
            $prefix = strtoupper($match[0][0]);
            ++$offset;
            $this->trivia->skipQuoted($sql, $offset, "'");

            return match ($prefix) {
                'N' => 'NCHAR_STRING',
                'X' => 'HEX_NUM',
                default => 'BIN_NUM',
            };
        }

        if (preg_match('/\G(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?|\G\d+[eE][+-]?\d+/A', $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return str_contains(strtolower($match[0]), 'e') ? 'FLOAT_NUM' : 'DECIMAL_NUM';
        }

        if (preg_match('/\G\d+/A', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return $this->integerToken($match[0]);
    }

    /**
     * Reads a keyword, function name, charset introducer or identifier.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when no word starts here
     */
    public function wordTokenAt(string $sql, int &$offset): ?string
    {
        if (preg_match('/\G_[A-Za-z0-9_]*/A', $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return strtolower($match[0]) === '_utf8mb4' ? 'UNDERSCORE_CHARSET' : 'IDENT';
        }

        if (preg_match('/\G[A-Za-z][A-Za-z0-9_$]*/A', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);
        $word = strtoupper($match[0]);
        $asFunction = ($sql[$offset] ?? null) === '(' ? ($this->functionTokens[$word] ?? null) : null;

        return $asFunction ?? $this->symbolTokens[$word] ?? 'IDENT';
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

        $offset += strlen($operator);
        $token = $this->symbolTokens[$operator] ?? $operator;

        return $token === 'OR_OR_SYM' ? 'OR2_SYM' : $token;
    }

    /**
     * Reports which integer token a run of digits widens to.
     *
     * MySQL picks the narrowest of three tokens that can hold the value, so the
     * boundaries are compared as text: the digits may be wider than an int.
     *
     * @param string $integer The digits as written
     *
     * @return string One of NUM, LONG_NUM or ULONGLONG_NUM
     */
    public function integerToken(string $integer): string
    {
        $normalized = ltrim($integer, '0');
        $normalized = $normalized === '' ? '0' : $normalized;

        if (strlen($normalized) < 10 || (strlen($normalized) === 10 && strcmp($normalized, '2147483647') <= 0)) {
            return 'NUM';
        }
        if (strlen($normalized) < 19 || (strlen($normalized) === 19 && strcmp($normalized, '9223372036854775807') <= 0)) {
            return 'LONG_NUM';
        }

        return 'ULONGLONG_NUM';
    }

    /**
     * Reports the operator written at the offset, longest first.
     *
     * @param string $sql Text being read
     * @param int $offset Where to look
     *
     * @return string|null The operator as written, or null when there is none
     */
    public function operatorAt(string $sql, int $offset): ?string
    {
        foreach (self::OPERATORS as $operator) {
            if (substr($sql, $offset, strlen($operator)) === $operator) {
                return $operator;
            }
        }

        return $this->isPunctuation($sql[$offset]) ? $sql[$offset] : null;
    }

    /**
     * Reports whether a single character is punctuation the grammar uses.
     *
     * @param string $character Character to inspect
     *
     * @return bool True for a punctuation character
     */
    public function isPunctuation(string $character): bool
    {
        return in_array($character, str_split(self::PUNCTUATION), true);
    }

    /**
     * Joins the token pairs MySQL's parser sees as one.
     *
     * @param list<string> $tokens Tokens as read
     *
     * @return list<string> Tokens with `WITH ROLLUP` collapsed
     */
    public function merged(array $tokens): array
    {
        $merged = [];
        $joined = false;
        foreach ($tokens as $index => $token) {
            if ($joined) {
                $joined = false;
                continue;
            }
            if ($token === 'WITH' && ($tokens[$index + 1] ?? null) === 'ROLLUP_SYM') {
                $merged[] = 'WITH_ROLLUP_SYM';
                $joined = true;
                continue;
            }
            $merged[] = $token;
        }

        return $merged;
    }
}
