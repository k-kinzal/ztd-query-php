<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Undetermined;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Reports an identity column whose type is not smallint, integer or bigint.
 *
 * Rule: PG-IDENTITY-TYPE-001. The server creates the identity sequence with
 * the column's type, and the sequence accepts only int2, int4 and int8: any
 * other built-in type, an array, a type with modifiers that is not one of the
 * three and an interval with fields are reported ("identity column type must
 * be smallint, integer, or bigint", init_params in sequence.c). A type known
 * by name only, a domain over an integer type included, depends on the
 * catalog and is not reported; an invalid type name is reported where it is
 * written.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html,
 * https://github.com/postgres/postgres/blob/REL_17_2/src/backend/commands/sequence.c. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IdentityColumns
{
    /**
     * Reports the identity type problem of a column of a declared type, or of no known type when null.
     */
    public function check(Derivation $derivation, ?TypeDescriptor $type): void
    {
        $admitted = $type === null || $type instanceof NamedOnPath || $type instanceof Undetermined
            || in_array($type, [Builtin::Int2, Builtin::Int4, Builtin::Int8], true);
        if (!$admitted) {
            $derivation->report(new DefinitionProblem(DefinitionRule::IdentityType));
        }
    }
}
