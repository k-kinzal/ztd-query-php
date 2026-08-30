<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Dialect;

use ZtdQuery\Platform\SqlPlaceholderEscaper;

/**
 * The pg sql pdo placeholder escaper, as sql placeholder escaper.
 */
final class PgSqlPdoPlaceholderEscaper implements SqlPlaceholderEscaper
{
    /**
     * @param PgSqlPdoRuns $runs Says where a run carried over as written ends
     */
    public function __construct(private readonly PgSqlPdoRuns $runs = new PgSqlPdoRuns())
    {
    }

    /**
     * Writes a statement so that PDO reads only its placeholders as placeholders.
     *
     * @param string $sql Statement being read, as written
     *
     * @return string The statement, with PostgreSQL's own question marks doubled
     */
    public function escape(string $sql): string
    {
        $result = '';
        $length = strlen($sql);
        $expectsOperand = true;

        for ($offset = 0; $offset < $length; $offset++) {
            $trivia = $this->runs->triviaEnd($sql, $offset);
            if ($trivia !== null) {
                $result .= substr($sql, $offset, $trivia - $offset);
                $offset = max($offset, $trivia - 1);
                continue;
            }
            $value = $this->runs->valueEnd($sql, $offset);
            if ($value !== null) {
                $result .= substr($sql, $offset, $value - $offset);
                $offset = max($offset, $value - 1);
                $expectsOperand = false;
                continue;
            }
            $written = $this->placeholderAt($sql, $offset, $expectsOperand);
            if ($written !== null) {
                $result .= $written['text'];
                $offset = max($offset, $written['end'] - 1);
                $expectsOperand = $written['expectsOperand'];
                continue;
            }
            $word = $this->runs->wordEnd($sql, $offset);
            if ($word !== null) {
                $text = substr($sql, $offset, $word - $offset);
                $expectsOperand = !str_ends_with($result, ':')
                    && self::keywordExpectsOperand(strtoupper($text));
                $result .= $text;
                $offset = max($offset, $word - 1);
                continue;
            }
            $number = $this->runs->numberEnd($sql, $offset);
            if ($number !== null) {
                $result .= substr($sql, $offset, $number - $offset);
                $offset = max($offset, $number - 1);
                $expectsOperand = false;
                continue;
            }

            $result .= $sql[$offset];
            $expectsOperand = self::leavesOperandExpected($sql[$offset], $expectsOperand);
        }

        return $result;
    }

    /**
     * Answers what a question mark or a colon here has to be written as.
     *
     * PDO reads a question mark as a placeholder, so PostgreSQL's own
     * operators written with one have to be doubled to stand for themselves;
     * a question mark where a value is expected is a placeholder and is left
     * alone. A double colon is a cast and never opens a placeholder.
     *
     * @param string $sql Statement being read, as written
     * @param int $offset Where to look
     * @param bool $expectsOperand Whether a value is what would come next
     *
     * @return array{text: string, end: int, expectsOperand: bool}|null What to write and where it left off, or null where neither is written there
     */
    public function placeholderAt(string $sql, int $offset, bool $expectsOperand): ?array
    {
        $character = $sql[$offset];
        $next = $sql[$offset + 1] ?? '';
        if ($character === ':') {
            return $next === ':' ? ['text' => '::', 'end' => $offset + 2, 'expectsOperand' => true] : null;
        }
        if ($character !== '?') {
            return null;
        }
        if ($next === '?') {
            return ['text' => '??', 'end' => $offset + 2, 'expectsOperand' => true];
        }
        if ($next === '|' || $next === '&') {
            return ['text' => '??' . $next, 'end' => $offset + 2, 'expectsOperand' => true];
        }
        if ($expectsOperand) {
            return ['text' => '?', 'end' => $offset + 1, 'expectsOperand' => false];
        }

        return ['text' => '??', 'end' => $offset + 1, 'expectsOperand' => true];
    }

    /**
     * Reports whether a value is what would come after this byte.
     *
     * @param string $character The byte, as written
     * @param bool $expectsOperand Whether a value was expected before it
     *
     * @return bool True when a value is what comes next
     */
    public static function leavesOperandExpected(string $character, bool $expectsOperand): bool
    {
        return match ($character) {
            ')', ']' => false,
            '(', '[', ',', ';', '.', '=', '<', '>', '!', '~', '+', '-', '*', '/', '%', '^', '|', '&', '#', '@' => true,
            default => $expectsOperand,
        };
    }

    /**
     * Reports whether a keyword is one something is written after.
     *
     * What follows an operator is a value; what follows a name is not. That is
     * what says whether a colon here opens a placeholder or is PostgreSQL's
     * own operator.
     *
     * @param string $keyword Keyword to look for
     *
     * @return bool What it answers
     */
    public static function keywordExpectsOperand(string $keyword): bool
    {
        return in_array($keyword, [
            'ALL',
            'AND',
            'ANY',
            'AS',
            'BETWEEN',
            'BY',
            'CASE',
            'CONFLICT',
            'DELETE',
            'DISTINCT',
            'DO',
            'ELSE',
            'FIRST',
            'FROM',
            'HAVING',
            'ILIKE',
            'IN',
            'INSERT',
            'INTO',
            'IS',
            'JOIN',
            'LAST',
            'LIKE',
            'LIMIT',
            'NOT',
            'OFFSET',
            'ON',
            'OR',
            'RETURNING',
            'SELECT',
            'SET',
            'SIMILAR',
            'SOME',
            'THEN',
            'UPDATE',
            'USING',
            'VALUE',
            'VALUES',
            'WHEN',
            'WHERE',
            'ZONE',
        ], true);
    }

    /**
     * Reports whether a name could open with this byte.
     *
     * @param string $char The char
     *
     * @return bool What it answers
     */
    public static function isIdentifierStart(string $char): bool
    {
        return $char >= 'A' && $char <= 'Z'
            || $char >= 'a' && $char <= 'z'
            || $char === '_';
    }

    /**
     * Reports whether a name could carry on with this byte.
     *
     * @param string $char The char
     *
     * @return bool What it answers
     */
    public static function isIdentifierContinuation(string $char): bool
    {
        return self::isIdentifierStart($char)
            || ctype_digit($char)
            || $char === '$';
    }

    /**
     * Reports whether one of PostgreSQL's escape strings opens here.
     *
     * @param string $sql Statement being read, as written
     * @param int $quotePosition The quote position
     *
     * @return bool What it answers
     */
    public static function isEscapeStringStart(string $sql, int $quotePosition): bool
    {
        $prefix = substr($sql, 0, $quotePosition);

        return (str_ends_with($prefix, 'E') || str_ends_with($prefix, 'e'))
            && (strlen($prefix) === 1 || !self::isIdentifierContinuation(substr($prefix, -2, 1)));
    }

    /**
     * Answers the delimiter a dollar-quoted run opens with.
     *
     * @param string $sql Statement being read, as written
     * @param int $position The position
     *
     * @return string|null What it answers
     */
    public static function dollarQuoteDelimiter(string $sql, int $position): ?string
    {
        $length = strlen($sql);
        $i = $position + 1;
        if ($i < $length && $sql[$i] === '$') {
            return '$$';
        }
        if ($i >= $length || !self::isIdentifierStart($sql[$i])) {
            return null;
        }

        while ($i < $length && (self::isIdentifierStart($sql[$i]) || ctype_digit($sql[$i]))) {
            $i++;
        }
        if ($i >= $length || $sql[$i] !== '$') {
            return null;
        }

        return substr($sql, $position, $i - $position) . '$';
    }
}
