<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Ordering;
use MySqlMemory\Value\Encoding;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * [NOT] LIKE: whether a string matches a pattern of `%` (any characters) and `_` (one character).
 *
 * Characters are matched one by one in the collation of the operation, in its character set; the escape character,
 * a backslash unless ESCAPE names another, makes the character after it literal. An empty or
 * NULL escape escapes nothing, and so does an escape of more than one byte in a binary collation of a multibyte character
 * set, which compares it with single bytes (verified on a live 8.4 server). An escape fixed for the statement but known only when it runs,
 * such as USER(), is checked when the first row is matched: one of more than one character is
 * ER_WRONG_ARGUMENTS, even for a NULL string.
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
     * @param bool $escapeDeferred Whether the escape is checked when the first row is matched rather than when the statement is resolved
     */
    public function __construct(
        public readonly Evaluable $operand,
        public readonly Evaluable $pattern,
        public readonly ?Evaluable $escape,
        public readonly Collation $collation,
        public readonly bool $negated,
        public readonly Domain $domain,
        public readonly bool $escapeDeferred = false,
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
     * Matches the string for a row; the escape is read before the string, and the pattern is not evaluated for a NULL string.
     *
     * @throws \MySqlMemory\Error\SqlError When an escape checked at the first row is more than one character
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $escape = $this->escape === null ? $this->symbol('\\') : $this->escapeText($frame);
        if ($this->collation->binaryOrder() && $this->collation->charset->maxLength > 1 && strlen($escape) > 1) {
            $escape = '';
        }
        $subject = $this->text($this->operand, $frame);
        $pattern = $subject === null ? null : $this->text($this->pattern, $frame);
        if ($subject === null || $pattern === null) {
            return null;
        }
        $matched = $this->match($this->characters($subject), 0, $this->tokens($this->characters($pattern), $escape), 0);

        return $matched !== $this->negated ? 1 : 0;
    }

    /**
     * Reads the escape character, once for the statement, and checks a deferred one.
     *
     * @throws \MySqlMemory\Error\SqlError When a deferred escape is more than one character
     */
    public function escapeText(Frame $frame): string
    {
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $escape = $this->escape === null ? null : $this->text($this->escape, $frame);
            if ($this->escapeDeferred && $escape !== null && count($this->characters($escape)) > 1) {
                throw StatementError::WrongArguments->error('ESCAPE');
            }
            $kept[$this] = [$escape];
        }

        return (string) $kept[$this][0];
    }

    /**
     * Reads the text of an operand in the character set of the collation, or answers null.
     */
    public function text(Evaluable $operand, Frame $frame): ?string
    {
        $domain = $operand->domain();
        $text = Convert::toText($operand->evaluate($frame), $domain);

        return $text === null ? null : Encoding::convert($text, $domain->kind === Kind::String ? $domain->collation->charset : Charset::known('utf8mb4'), $this->collation->charset);
    }

    /**
     * Answers an ASCII symbol in the character set of the collation.
     */
    public function symbol(string $symbol): string
    {
        return Encoding::convert($symbol, Charset::known('ascii'), $this->collation->charset);
    }

    /**
     * Splits a string into characters, or bytes for a binary collation.
     *
     * @return list<string>
     */
    public function characters(string $text): array
    {
        return Encoding::characters($text, $this->collation->charset);
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
            } elseif ($character === $this->symbol('%') || $character === $this->symbol('_')) {
                $tokens[] = [$character === $this->symbol('%') ? '%' : '_', ''];
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
