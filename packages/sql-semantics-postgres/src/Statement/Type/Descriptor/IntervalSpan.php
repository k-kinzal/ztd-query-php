<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * The interval type restricted to a set of fields, a fractional-second precision, or both.
 *
 * Source: https://www.postgresql.org/docs/17/datatype-datetime.html#DATATYPE-INTERVAL-INPUT.
 *
 * @visibility public
 * @example Naming a restricted interval type
 *     $span = new \SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\IntervalSpan(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\IntervalFields::DayToSecond, 3);
 *     $span->name() // => 'interval day to second(3)'
 */
final class IntervalSpan implements TypeDescriptor
{
    use Snapshot;

    /**
     * @param IntervalFields|null $fields The fields the interval is restricted to
     * @param int|null $precision The number of fractional second digits kept, 0 to 6
     */
    public function __construct(public readonly ?IntervalFields $fields, public readonly ?int $precision = null)
    {
        Check::input($fields !== null || $precision !== null, 'A restricted interval type has fields or a precision.');
        Check::input($precision === null || ($precision >= 0 && $precision <= 6), 'An interval precision is 0 to 6.');
        Check::input($precision === null || $fields === null || $fields->seconds(), 'Only an interval with a seconds field has a precision next to its fields.');
    }

    /**
     * Names the type as the server displays it.
     */
    public function name(): string
    {
        $precision = $this->precision === null ? '' : '(' . $this->precision . ')';

        return 'interval' . ($this->fields === null ? '' : ' ' . strtolower($this->fields->value)) . $precision;
    }
}
