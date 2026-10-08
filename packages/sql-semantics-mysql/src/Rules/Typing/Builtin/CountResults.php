<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Resolves the results of functions that count or locate: a BIGINT of the display length each reports.
 *
 * GROUPING() is a BIGINT of 21 digits, verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_grouping.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class CountResults
{
    private const LENGTHS = [
        'LENGTH' => 10, 'OCTET_LENGTH' => 10, 'BIT_LENGTH' => 10, 'CHAR_LENGTH' => 10, 'CHARACTER_LENGTH' => 10,
        'ASCII' => 3, 'LOCATE' => 11, 'INSTR' => 11, 'STRCMP' => 2, 'FIELD' => 3, 'FIND_IN_SET' => 3,
        'CONNECTION_ID' => 21, 'ROW_COUNT' => 21, 'FOUND_ROWS' => 21, 'COERCIBILITY' => 10, 'SIGN' => 21, 'ISNULL' => 1, 'GROUPING' => 21,
    ];

    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $rules = [];
        foreach (self::LENGTHS as $name => $length) {
            $rules[$name] = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $length);
        }
        $rules['LAST_INSERT_ID'] = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 21, true);

        return $rules;
    }
}
