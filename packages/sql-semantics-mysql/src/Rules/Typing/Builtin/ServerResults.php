<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Resolves the results of the miscellaneous, locking and performance schema functions.
 *
 * ANY_VALUE() has the type its argument settles to alone, as COALESCE() of one argument does;
 * NAME_CONST() has the type of its value, read as a signed number when it is an integer.
 * BENCHMARK(), GET_LOCK(), RELEASE_LOCK() and IS_FREE_LOCK() answer a BIGINT of one digit, SLEEP()
 * and RELEASE_ALL_LOCKS() a BIGINT of 21, and IS_USED_LOCK(), PS_CURRENT_THREAD_ID() and
 * PS_THREAD_ID() an unsigned BIGINT of 21 (MySQL 5.6 answers IS_USED_LOCK() as a signed BIGINT of
 * 10). ICU_VERSION() is a utf8mb3 system constant of 4 characters, FORMAT_BYTES() and
 * FORMAT_PICO_TIME() coercible utf8mb3 strings of 11, and ROLES_GRAPHML() a utf8mb3 LONGTEXT
 * system constant (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/performance-schema-functions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/information-functions.html.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class ServerResults
{
    /**
     * Answers the rule of each function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $integer = static fn (int $length, bool $unsigned = false): Closure => static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $length, $unsigned);
        $text = static fn (int $length, Field $field, Coercibility $coercibility): Closure => static fn (Invocation $call): Domain => Domain::string($length, Collation::known('utf8mb3_general_ci'), $field, $coercibility);

        return [
            'ANY_VALUE' => static fn (Invocation $call): ?Domain => $call->aggregation()->of($call->domains, 'any_value', $call->derivation),
            'NAME_CONST' => fn (Invocation $call): Domain => $this->constant($call->domain(1)),
            'BENCHMARK' => $integer(1),
            'SLEEP' => $integer(21),
            'GET_LOCK' => $integer(1),
            'RELEASE_LOCK' => $integer(1),
            'IS_FREE_LOCK' => $integer(1),
            'IS_USED_LOCK' => static fn (Invocation $call): Domain => $call->derivation->context->profile->grammar === GrammarRelease::MySql5651 ? Domain::integer(Field::LongLong, 10) : Domain::integer(Field::LongLong, 21, true),
            'RELEASE_ALL_LOCKS' => $integer(21),
            'PS_CURRENT_THREAD_ID' => $integer(21, true),
            'PS_THREAD_ID' => $integer(21, true),
            'ICU_VERSION' => $text(4, Field::VarString, Coercibility::SystemConstant),
            'FORMAT_BYTES' => $text(11, Field::VarString, Coercibility::Coercible),
            'FORMAT_PICO_TIME' => $text(11, Field::VarString, Coercibility::Coercible),
            'ROLES_GRAPHML' => $text(50331648, Field::LongBlob, Coercibility::SystemConstant),
        ];
    }

    /**
     * Answers the type of the value of NAME_CONST(): the type of the constant, an integer read as signed.
     */
    public function constant(Domain $value): Domain
    {
        return new Domain($value->kind, $value->field, $value->length, $value->decimals, false, $value->collation, $value->members, $value->coercibility, $value->display);
    }
}
