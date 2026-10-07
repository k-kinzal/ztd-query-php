<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * [NOT] LIKE: whether a string matches a pattern of `%` (any characters) and `_` (one character).
 *
 * Characters are matched one by one in the collation of the operation; the escape character,
 * a backslash unless ESCAPE names another, makes the character after it literal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-comparison-functions.html#operator_like.
 *
 * @visibility MySqlMemory
 */
final class Pattern implements Evaluable
{
    /**
     * @param Evaluable $operand The string matched
     * @param Evaluable $pattern The pattern
     * @param Evaluable|null $escape The escape character, or null for a backslash
     * @param Collation $collation The collation characters are matched in
     * @param bool $negated Whether NOT is written
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(
        public readonly Evaluable $operand,
        public readonly Evaluable $pattern,
        public readonly ?Evaluable $escape,
        public readonly Collation $collation,
        public readonly bool $negated,
        public readonly Domain $domain,
    ) {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Matches the string for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $subject = Convert::toText($this->operand->evaluate($frame), $this->operand->domain());
        $pattern = Convert::toText($this->pattern->evaluate($frame), $this->pattern->domain());
        if ($subject === null || $pattern === null) {
            return null;
        }
        $escape = '\\';
        if ($this->escape !== null) {
            $escape = (string) Convert::toText($this->escape->evaluate($frame), $this->escape->domain());
            if ($this->characters($escape) === [] && $escape !== '') {
                throw ErrorCode::WrongArguments->error('ESCAPE');
            }
        }
        $matched = $this->match($this->characters($subject), 0, $this->tokens($this->characters($pattern), $escape), 0);

        return $matched !== $this->negated ? 1 : 0;
    }

    /**
     * Splits a string into characters, or bytes for a binary collation.
     *
     * @return list<string>
     */
    public function characters(string $text): array
    {
        if ($this->collation->charset->maxLength === 1 || !mb_check_encoding($text, 'UTF-8')) {
            return $text === '' ? [] : str_split($text);
        }

        return mb_str_split($text, 1, 'UTF-8');
    }

    /**
     * Reads a pattern into tokens: '%' and '_' wildcards, and literal characters.
     *
     * @param list<string> $pattern
     * @return list<array{string, string}>
     */
    public function tokens(array $pattern, string $escape): array
    {
        $tokens = [];
        for ($i = 0, $count = count($pattern); $i < $count; $i++) {
            $character = $pattern[$i];
            if ($character === $escape && $escape !== '' && $i + 1 < $count) {
                $tokens[] = ['c', $pattern[++$i]];
            } elseif ($character === '%' || $character === '_') {
                $tokens[] = [$character, ''];
            } else {
                $tokens[] = ['c', $character];
            }
        }

        return $tokens;
    }

    /**
     * Matches characters from a position against tokens from a position.
     *
     * @param list<string> $subject
     * @param list<array{string, string}> $tokens
     */
    public function match(array $subject, int $at, array $tokens, int $token): bool
    {
        $count = count($tokens);
        while ($token < $count) {
            [$kind, $character] = $tokens[$token];
            if ($kind === '%') {
                while ($token + 1 < $count && $tokens[$token + 1][0] === '%') {
                    $token++;
                }
                if ($token + 1 === $count) {
                    return true;
                }
                for ($start = $at, $length = count($subject); $start <= $length; $start++) {
                    if ($this->match($subject, $start, $tokens, $token + 1)) {
                        return true;
                    }
                }

                return false;
            }
            if (!isset($subject[$at]) || ($kind === 'c' && Ordering::of($this->collation)->compare($subject[$at], $character) !== 0)) {
                return false;
            }
            $at++;
            $token++;
        }

        return $at === count($subject);
    }
}
