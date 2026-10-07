<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves the results of date functions: DATE is a date, YEAR a year, each other part of a date or time a BIGINT as wide as its digits and a sign.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class DateResults
{
    private const PARTS = [
        'MONTH' => 3, 'DAY' => 3, 'DAYOFMONTH' => 3, 'HOUR' => 4, 'MINUTE' => 3, 'SECOND' => 3,
        'MICROSECOND' => 21, 'QUARTER' => 2, 'DAYOFYEAR' => 4, 'DAYOFWEEK' => 2, 'WEEKDAY' => 2,
    ];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $rules = [
            'DATE' => static fn (Invocation $call): Domain => new Domain(Kind::Date, Field::Date, 10),
            'YEAR' => static fn (Invocation $call): Domain => new Domain(Kind::Year, Field::Year, 4, 0, true),
        ];
        foreach (self::PARTS as $name => $length) {
            $rules[$name] = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $length);
        }

        return $rules;
    }
}
