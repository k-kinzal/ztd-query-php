<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Evaluation\Evaluable;

/**
 * What a column stores when an insert names no value for it.
 *
 * A column has no default (an insert must name a value, or under a non-strict mode stores the
 * implicit default of its type), a constant, an expression evaluated for each row, or the time
 * of the statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html.
 *
 * @visibility MySqlMemory
 */
final class Fill
{
    /**
     * @param bool $declared Whether the column has a default, explicit or implied
     * @param int|float|string|null $value The constant default, held as the column's kind says
     * @param Evaluable|null $expression The default expression, evaluated per row
     * @param bool $now Whether the default is the current time of the statement
     * @param string $text The default as SHOW COLUMNS reports it, or null for none
     */
    public function __construct(
        public readonly bool $declared,
        public readonly int|float|string|null $value = null,
        public readonly ?Evaluable $expression = null,
        public readonly bool $now = false,
        public readonly ?string $text = null,
    ) {
    }

    /**
     * Answers the default of a column that has none.
     */
    public static function none(): self
    {
        return new self(false);
    }

    /**
     * Answers a constant default.
     */
    public static function constant(int|float|string|null $value, ?string $text): self
    {
        return new self(true, $value, null, false, $text);
    }
}
