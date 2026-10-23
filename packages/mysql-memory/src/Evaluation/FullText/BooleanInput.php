<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\FullText;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Value\Encoding;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Reads the documented Boolean search operators from the evaluated AGAINST value.
 *
 * Search-language syntax is runtime data, like a regular expression or JSON path.
 * This reader never receives the SQL statement, its source, or its parser nodes.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/fulltext-boolean.html.
 *
 * @visibility MySqlMemory
 */
final class BooleanInput
{
    /**
     * @param list<string> $tokens Search words, phrases and operator punctuation
     */
    public function __construct(public readonly array $tokens, public readonly Words $words, public readonly bool $myIsam)
    {
    }

    /**
     * @return list<Term> Search alternatives, including required and excluded terms
     */
    public static function read(string $text, Charset $charset, Words $words, bool $myIsam): array
    {
        $text = Encoding::convert($text, $charset, Charset::known('utf8mb4'));
        preg_match_all('/"[^"\\n]*(?:"|$)|[+~><()@*\-]|[\\p{L}\\p{N}_]+(?:\x27[\\p{L}\\p{N}_]+)*/u', $text, $matches);
        $position = 0;
        return (new self($matches[0], $words, $myIsam))->group($position, false);
    }

    /**
     * @return list<Term> Terms inside one parenthesized level
     * @throws SqlError When a Boolean operator has no operand or has incompatible punctuation
     */
    public function group(int &$position, bool $nested): array
    {
        $terms = [];
        while (isset($this->tokens[$position])) {
            $token = $this->tokens[$position];
            if ($token === ')') {
                $position++;
                if (!$nested && !$this->myIsam) {
                    throw $this->error("')', expecting \$end");
                }
                return $terms;
            }
            $operator = $this->operator($position);
            $token = $this->tokens[$position] ?? null;
            if ($token === null) {
                if (!$this->myIsam) {
                    throw $this->error('$end');
                }
                return $terms;
            }
            $position++;
            if ($token === '(') {
                $terms[] = new Term(children: $this->group($position, true), operator: $operator);
            } elseif ($token === '@' || $token === ')') {
                if (!$this->myIsam) {
                    throw $this->error("'" . $token . "', expecting \$end");
                }
            } else {
                $terms[] = $this->term($token, $operator, $position);
            }
        }
        if ($nested && !$this->myIsam) {
            throw $this->error('$end');
        }
        return $terms;
    }

    /**
     * Reads a leading modifier, preserving MyISAM's repeated relevance modifiers.
     *
     * @throws SqlError When InnoDB sees multiple leading operators
     */
    public function operator(int &$position): string
    {
        $operator = '';
        while (isset($this->tokens[$position]) && in_array($this->tokens[$position], ['+', '-', '~', '>', '<', '*'], true)) {
            $token = $this->tokens[$position++];
            if ($token === '*') {
                continue;
            }
            if ($operator !== '' && !$this->myIsam) {
                throw $this->error("'" . $token . "'");
            }
            $operator .= $token;
        }
        if (!$this->myIsam && !isset($this->tokens[$position]) && ($this->tokens[$position - 1] ?? null) === '*') {
            throw $this->error("\$end, expecting FTS_TERM or FTS_NUMB or '*'");
        }
        return $operator;
    }

    /**
     * Reads a word, a trailing wildcard, or a phrase with an optional proximity distance.
     *
     * @throws SqlError When a proximity operator has no numeric distance
     */
    public function term(string $token, string $operator, int &$position): Term
    {
        $phrase = str_starts_with($token, '"');
        $tokens = $this->words->split(trim($token, '"'), Charset::known('utf8mb4'));
        $prefix = !$phrase && ($this->tokens[$position] ?? null) === '*';
        if ($prefix) {
            $position++;
        }
        $distance = null;
        if ($phrase && !$this->myIsam && ($this->tokens[$position] ?? null) === '@') {
            $position++;
            $number = $this->tokens[$position++] ?? '';
            if ($number === '' || !ctype_digit($number)) {
                throw $this->error($number === '' ? '$end, expecting FTS_NUMB' : 'FTS_TERM, expecting FTS_NUMB');
            }
            $distance = (int) $number;
        }
        return new Term($tokens, operator: $operator, prefix: $prefix, distance: $distance, phrase: $phrase);
    }

    /**
     * Produces the search-language diagnostic instead of an SQL source excerpt.
     */
    public function error(string $unexpected): SqlError
    {
        return new SqlError(StatementError::ParseError, 'syntax error, unexpected ' . $unexpected);
    }
}
