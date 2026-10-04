<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Typing;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Answers what an expression knows about a value of a declared type.
 *
 * Rule: PG-DECLARED-TYPE-001. Scope: every position that reads a declared
 * column. A column declared with a catalog type has that type. A column
 * declared with a type known by name only (`NamedOnPath`), or an array over
 * one, has a type the context cannot identify: operators, functions and
 * coercions over it depend on the missing inputs the descriptor carries.
 * Minimum precision: `Known` for every other descriptor.
 * Source: https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH.
 * Termination: one step per array level. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DeclaredTypes
{
    /**
     * Answers the type fact of a value of a declared type.
     */
    public function fact(TypeDescriptor $type): TypeFact
    {
        $element = $type;
        while ($element instanceof ArrayOf) {
            $element = $element->element;
        }

        return $element instanceof NamedOnPath ? new Dependent($element->missing) : new Known($type);
    }
}
