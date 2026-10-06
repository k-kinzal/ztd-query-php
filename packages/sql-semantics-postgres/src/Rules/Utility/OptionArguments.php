<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Utility;

use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Toggle;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption;

/**
 * Reads the value of a utility option the way the server does.
 *
 * Rule: PG-UTILITY-OPTION-VALUE-001. A Boolean option without a value is
 * true. With a value, the integers 0 and 1 are false and true, and a word,
 * a string or one of the keywords TRUE, FALSE and ON is true for `true` and
 * `on` and false for `false` and `off`, compared without regard to case;
 * any other value is not Boolean. An integer option takes an integer
 * constant that fits in 32 bits: the lexer reads a larger integer as a
 * decimal number, which is not an integer. When an option is written more
 * than once, the last occurrence decides. Termination: one pass over a
 * finite list.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html, https://www.postgresql.org/docs/17/sql-vacuum.html,
 * https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class OptionArguments
{
    /**
     * Answers the Boolean value of an option, or null when its value is not Boolean.
     */
    public function boolean(UtilityOption $option): ?bool
    {
        $argument = $option->argument;
        if ($argument === null) {
            return true;
        }
        if ($argument instanceof SignedNumber) {
            $integer = $this->integer($option);

            return match ($integer) {
                0 => false,
                1 => true,
                default => null,
            };
        }
        $text = match (true) {
            $argument instanceof Toggle => $argument->text(),
            $argument instanceof Word => $argument->word->value,
            $argument instanceof StringConstant => $argument->value,
        };

        return match (strtolower($text)) {
            'true', 'on' => true,
            'false', 'off' => false,
            default => null,
        };
    }

    /**
     * Answers the integer value of an option, or null when it has no value or the value is not an integer that fits in 32 bits.
     */
    public function integer(UtilityOption $option): ?int
    {
        $argument = $option->argument;
        if (!$argument instanceof SignedNumber || !$argument->magnitude instanceof IntegerConstant) {
            return null;
        }
        $digits = $argument->magnitude->digits;

        return $argument->negative ? -(int) $digits : (int) $digits;
    }

    /**
     * Answers the Boolean value of the last option of a name, or a default when none is written or its value is not Boolean.
     *
     * @param list<UtilityOption> $options
     */
    public function enabled(array $options, string $name, bool $default): bool
    {
        $option = (new OptionRules())->find($options, $name);

        return $option === null ? $default : ($this->boolean($option) ?? $default);
    }
}
