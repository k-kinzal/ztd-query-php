<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Typing\Declared;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;

/**
 * The type of a parameter or local variable of a stored program, as declared.
 *
 * A variable holds a value of its type as a column of that type does, and can be NULL. A string
 * type without a character set takes the collation of the database of the program, and one
 * with a COLLATE clause that collation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/declare-local-variable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility MySqlMemory
 */
final class VariableDomain
{
    /**
     * Answers the type a declaration gives a variable.
     *
     * @param Collation $default The collation of the database of the program
     */
    public static function declared(TypeName $type, ?CollationName $collation, Collation $default): Domain
    {
        $named = $collation?->name === null ? null : Collation::named($collation->name->value);
        $domain = (new Declared($default))->domain($type, $named)->withNullable(true);
        if ($domain->kind !== Kind::String) {
            return $domain;
        }

        return new Domain($domain->kind, $domain->field, $domain->length, 0, $domain->unsigned, $domain->collation, true, $domain->members, $domain->coercibility);
    }
}
