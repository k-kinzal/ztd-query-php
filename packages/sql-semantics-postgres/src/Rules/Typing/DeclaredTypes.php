<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Typing;

use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Undetermined;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Answers what an expression knows about a value of a declared type.
 *
 * Rule: PG-DECLARED-TYPE-001. Scope: every position that reads a declared
 * column. A column declared with a catalog type has that type. A column
 * declared with a type known by name only (`NamedOnPath`), or an array over
 * one, has a type the context cannot identify: operators, functions and
 * coercions over it depend on the missing inputs the descriptor carries;
 * so does a column a query defines with a type those inputs settle
 * (`Undetermined`). Minimum precision: `Known` for every other descriptor.
 * A column defined by a query (CREATE TABLE AS, SELECT INTO, CREATE VIEW)
 * has the type of its output expression; an output of type `unknown` (an
 * untyped string constant or NULL) is resolved to `text`
 * (`resolveTargetListUnknowns`), and an output whose type depends on
 * missing inputs is `Undetermined` over them. An output of an invalid type
 * has no column type: the server rejects the statement.
 * Source: https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH,
 * https://www.postgresql.org/docs/17/typeconv-select.html.
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

        return $element instanceof NamedOnPath || $element instanceof Undetermined ? new Dependent($element->missing) : new Known($type);
    }

    /**
     * Answers the type a query output of the given fact gives the column it defines, or null when the output has no type.
     */
    public function defined(TypeFact $fact): ?TypeDescriptor
    {
        return match (true) {
            $fact instanceof Known => $fact->descriptor === Builtin::Unknown ? Builtin::Text : $fact->descriptor,
            $fact instanceof NullOnly => Builtin::Text,
            $fact instanceof Dependent => new Undetermined($fact->missing),
            default => null,
        };
    }
}
