<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;

/**
 * Derives a condition of a definition: a CHECK constraint, a partial index predicate, a trigger or rule WHEN, a policy expression.
 *
 * Rule: PG-TABLE-CONDITION-001. The condition is derived in the environment
 * the definition gives. Its value is coerced to boolean: a condition of a
 * known type other than boolean (an untyped string constant becomes boolean)
 * is reported as "argument of CHECK must be type boolean". A condition of a
 * type that is not known is not reported.
 * Source: https://www.postgresql.org/docs/17/typeconv-query.html,
 * https://www.postgresql.org/docs/17/sql-createtable.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Conditions
{
    /**
     * Derives the condition and reports a known type that is not boolean.
     *
     * @param string $clause The clause the server names in its message, such as CHECK or WHERE
     */
    public function derive(Derivation $derivation, Scalar $condition, Environment $environment, string $clause): ScalarFact
    {
        $fact = $derivation->scalar($condition, $environment);
        if ($fact->type instanceof Known && $fact->type->descriptor !== Builtin::Bool && $fact->type->descriptor !== Builtin::Unknown) {
            $derivation->report(new DefinitionProblem(DefinitionRule::NotBoolean, new Name($clause)));
        }

        return $fact;
    }
}
