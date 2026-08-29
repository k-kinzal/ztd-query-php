<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Grammar\Lexical\LexicalException;

/**
 * Reads PostgreSQL text back into the parser tokens the server would produce.
 *
 * This is the inverse of realization and the reason realization can be trusted:
 * SQL written from a terminal sequence is read back here, and the two sequences
 * must agree. PostgreSQL's rules are its own — a string may be spelled six ways,
 * block comments nest, and an operator is the longest run of operator characters
 * that does not start a comment — so a tokenizer that merely looked plausible
 * would agree with a generator that was equally wrong.
 */
final class PgTokenizer
{
    private const OPERATOR_CHARACTERS = '+-*/<>=~!@#%^&|`?';

    private const PUNCTUATION = '%()*+,-./:;<=>[]^';

    private const FIXED_OPERATORS = ['::', '..', ':=', '=>', '<=', '>=', '<>', '!='];

    /** @readonly */
    private PgLookahead $lookahead;

    /**
     * @param array<string, string> $keywordTokens Terminal name by upper-cased keyword
     * @param PgLookahead $lookahead Substitutions the parser frontend makes
     */
    public function __construct(
        private readonly array $keywordTokens,
        PgLookahead $lookahead,
    ) {
        $this->lookahead = $lookahead;
    }

    /**
     * Reads SQL text into the tokens PostgreSQL's own lexer would produce.
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
                    throw LexicalException::noProgress('PostgreSQL', $startedAt, $sql);
                }

                continue;
            }

            $token = $this->tokenAt($sql, $offset);
            if ($token === null) {
                throw LexicalException::unsupportedInput('PostgreSQL', $offset, $sql);
            }

            if ($offset <= $startedAt) {
                throw LexicalException::noProgress('PostgreSQL', $startedAt, $sql);
            }

            $tokens[] = $token;
        }

        return $this->lookahead->applied($tokens);
    }

    /**
     * Reads the one token that starts at the offset, advancing past it.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when nothing here is a token
     *
     * @throws LexicalException When a quoted or dollar-quoted run never closes
     */
    public function tokenAt(string $sql, int &$offset): ?string
    {
        return $this->quotedTokenAt($sql, $offset)
            ?? $this->dollarTokenAt($sql, $offset)
            ?? $this->numericTokenAt($sql, $offset)
            ?? $this->wordTokenAt($sql, $offset)
            ?? $this->operatorTokenAt($sql, $offset);
    }

    /**
     * Reads a quoted identifier or string in any of the prefixes PostgreSQL accepts.
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
        if ($sql[$offset] === '"') {
            $this->skipQuoted($sql, $offset, '"');

            return 'IDENT';
        }

        if ($sql[$offset] === "'") {
            $this->skipQuoted($sql, $offset, "'");

            return 'SCONST';
        }

        foreach ([['/\G[Uu]&"/A', 2, '"', 'IDENT'], ['/\G[Uu]&\'/A', 2, "'", 'SCONST'],
            ['/\G[Ee]\'/A', 1, "'", 'SCONST'], ['/\G[Bb]\'/A', 1, "'", 'BCONST'],
            ['/\G[Xx]\'/A', 1, "'", 'XCONST']] as [$pattern, $skip, $quote, $token]) {
            if (preg_match($pattern, $sql, $match, 0, $offset) !== 1) {
                continue;
            }

            $offset += $skip;
            $this->skipQuoted($sql, $offset, $quote);

            return $token;
        }

        return null;
    }

    /**
     * Reads a dollar-quoted string or a positional parameter.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when neither starts here
     *
     * @throws LexicalException When a dollar-quoted string never closes
     */
    public function dollarTokenAt(string $sql, int &$offset): ?string
    {
        if (preg_match('/\G\$(?:[A-Za-z_][A-Za-z0-9_]*)?\$/A', $sql, $match, 0, $offset) === 1) {
            $delimiter = $match[0];
            $end = strpos($sql, $delimiter, $offset + strlen($delimiter));
            if ($end === false) {
                throw LexicalException::unterminatedDollarQuotedString('PostgreSQL');
            }
            $offset = $end + strlen($delimiter);

            return 'SCONST';
        }

        if (preg_match('/\G\$[1-9][0-9]*/A', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return 'PARAM';
    }

    /**
     * Reads an integer or a float literal.
     *
     * @param string $sql Text being read
     * @param int $offset Where to read from; moved past what was consumed
     *
     * @return string|null The token name, or null when no number starts here
     */
    public function numericTokenAt(string $sql, int &$offset): ?string
    {
        $float = '/\G(?:\d+\.\d*|\.\d+|\d+)[eE][+-]?\d+|\G(?:\d+\.\d*|\.\d+)/A';
        if (preg_match($float, $sql, $match, 0, $offset) === 1) {
            $offset += strlen($match[0]);

            return 'FCONST';
        }

        if (preg_match('/\G\d+/A', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return 'ICONST';
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
        if (preg_match('/\G[A-Za-z_][A-Za-z0-9_$]*/A', $sql, $match, 0, $offset) !== 1) {
            return null;
        }

        $offset += strlen($match[0]);

        return $this->keywordTokens[strtoupper($match[0])] ?? 'IDENT';
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
     * PostgreSQL nests block comments, so the depth is counted rather than the
     * first `*​/` being taken as the end.
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

        $depth = 1;
        $offset += 2;
        while ($offset < strlen($sql) && $depth > 0) {
            if (substr($sql, $offset, 2) === '/*') {
                ++$depth;
                $offset += 2;
            } elseif (substr($sql, $offset, 2) === '*/') {
                --$depth;
                $offset += 2;
            } else {
                ++$offset;
            }
        }

        if ($depth !== 0) {
            throw LexicalException::unterminatedBlockComment('PostgreSQL');
        }

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

        throw LexicalException::unterminatedQuotedToken('PostgreSQL', $sql);
    }

    /**
     * Reports the operator written at the offset and the token it stands for.
     *
     * A user-defined operator is the longest run of operator characters, but the
     * run stops where a comment would start: `+/*` is a plus and a comment, not
     * a three-character operator.
     *
     * @param string $sql Text being read
     * @param int $offset Where to look
     *
     * @return array{string, string}|null The operator and its token, or null when there is none
     */
    public function operatorAt(string $sql, int $offset): ?array
    {
        foreach (self::FIXED_OPERATORS as $operator) {
            if (substr($sql, $offset, strlen($operator)) === $operator) {
                return [$operator, $this->fixedOperator($operator) ?? 'Op'];
            }
        }

        $character = $sql[$offset];
        if (!str_contains(self::OPERATOR_CHARACTERS, $character)) {
            return $this->isPunctuation($character) ? [$character, $character] : null;
        }

        $end = $offset + strspn($sql, self::OPERATOR_CHARACTERS, $offset);
        foreach (['/*', '--'] as $commentOpening) {
            $opensAt = strpos($sql, $commentOpening, $offset + 1);
            if ($opensAt !== false && $opensAt < $end) {
                $end = $opensAt;
            }
        }

        $lexeme = substr($sql, $offset, $end - $offset);

        return strlen($lexeme) === 1 && $this->isPunctuation($lexeme)
            ? [$lexeme, $lexeme]
            : [$lexeme, 'Op'];
    }

    /**
     * Reports the token a spelling with a name of its own stands for.
     *
     * @param string $operator Operator as written
     *
     * @return string|null The token name, or null when the operator has none
     */
    public function fixedOperator(string $operator): ?string
    {
        return match ($operator) {
            '::' => 'TYPECAST',
            '..' => 'DOT_DOT',
            ':=' => 'COLON_EQUALS',
            '=>' => 'EQUALS_GREATER',
            '<>', '!=' => 'NOT_EQUALS',
            '<=' => 'LESS_EQUALS',
            '>=' => 'GREATER_EQUALS',
            default => null,
        };
    }

    /**
     * Reports whether a single character is punctuation that stands for itself.
     *
     * @param string $character Character to inspect
     *
     * @return bool True for a punctuation character
     */
    public function isPunctuation(string $character): bool
    {
        return strlen($character) === 1 && str_contains(self::PUNCTUATION, $character);
    }
}
