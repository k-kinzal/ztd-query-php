<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * How a regular expression matches: the match_type of a call, and the flags a pattern sets inline.
 *
 * Matching is case-insensitive when the collation of the subject and the pattern is (a `_ci`
 * collation), unless match_type says otherwise. The match_type letters are c (case-sensitive),
 * i (case-insensitive), m (multiple lines: ^ and $ match at line terminators), n (. matches line
 * terminators) and u (only the line feed ends a line); when c and i conflict, the last one wins.
 * Any other letter is an error (ER_WRONG_ARGUMENTS). A pattern changes them with (?dimsuwx-dimsuwx):
 * d is u, s is n, x allows white space and # comments, w selects Unicode word boundaries.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html#function_regexp-like,
 * https://unicode-org.github.io/icu/userguide/strings/regexp.html#flag-options.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Mode
{
    /**
     * @param bool $caseless Whether letters match without regard to case
     * @param bool $multiline Whether ^ and $ match at line terminators
     * @param bool $dotAll Whether . matches a line terminator
     * @param bool $unixLines Whether only the line feed ends a line
     * @param bool $comments Whether white space and # comments are ignored
     * @param bool $words Whether \b finds Unicode word boundaries
     */
    public function __construct(
        public readonly bool $caseless = false,
        public readonly bool $multiline = false,
        public readonly bool $dotAll = false,
        public readonly bool $unixLines = false,
        public readonly bool $comments = false,
        public readonly bool $words = false,
    ) {
    }

    /**
     * Answers the mode of a call from the collation of its subject and pattern and its match_type.
     *
     * @param string $function The function as error messages name it
     *
     * @throws SqlError When match_type holds a letter that is not a flag
     */
    public static function of(Collation $collation, string $flags, string $function): self
    {
        $mode = new self(str_contains($collation->name, '_ci'));
        foreach ($flags === '' ? [] : str_split($flags) as $flag) {
            $mode = match ($flag) {
                'c' => $mode->with('i', false),
                'i' => $mode->with('i', true),
                'm' => $mode->with('m', true),
                'n' => $mode->with('s', true),
                'u' => $mode->with('d', true),
                default => throw StatementError::WrongArguments->error($function),
            };
        }

        return $mode;
    }

    /**
     * Answers the mode with an inline flag set or cleared: d, i, m, s, u, w or x.
     */
    public function with(string $flag, bool $on): self
    {
        return new self(
            $flag === 'i' ? $on : $this->caseless,
            $flag === 'm' ? $on : $this->multiline,
            $flag === 's' ? $on : $this->dotAll,
            $flag === 'd' ? $on : $this->unixLines,
            $flag === 'x' ? $on : $this->comments,
            $flag === 'w' ? $on : $this->words,
        );
    }

    /**
     * Answers a key that tells modes apart.
     */
    public function key(): string
    {
        return implode('', array_map(static fn (bool $flag): string => $flag ? '1' : '0', [$this->caseless, $this->multiline, $this->dotAll, $this->unixLines, $this->comments, $this->words]));
    }
}
