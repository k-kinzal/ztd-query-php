<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;

/**
 * Derives and writes row security policies.
 *
 * Rule: PG-POLICY-001. The table is resolved (PG-TABLE-TARGET-001) and is
 * the relation fact of the statement. The USING and WITH CHECK expressions
 * are conditions (PG-TABLE-CONDITION-001) where the table is the only visible
 * relation ("expressions ... can refer to columns of the table"). The word
 * after AS must be PERMISSIVE or RESTRICTIVE ("unrecognized row security
 * option"). Source: https://www.postgresql.org/docs/17/sql-createpolicy.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Policies
{
    /**
     * Resolves the table and derives the conditions.
     */
    public function derive(Relation $policy, QualifiedName $table, ?Scalar $using, ?Scalar $check, Derivation $derivation): void
    {
        $targets = new Targets();
        $fact = $derivation->target($policy, $targets->resolve($derivation, $table));
        $scope = $targets->scope($derivation, $policy, $table, $fact->shape, $targets->implicit($fact));
        foreach ([$using, $check] as $condition) {
            if ($condition !== null) {
                (new Conditions())->derive($derivation, $condition, $scope, 'POLICY');
            }
        }
    }

    /**
     * Reports a word after AS other than permissive and restrictive.
     */
    public function mode(?Name $mode, Derivation $derivation): void
    {
        if ($mode !== null && $mode->value !== 'permissive' && $mode->value !== 'restrictive') {
            $derivation->report(new DefinitionProblem(DefinitionRule::RowSecurityOption, $mode));
        }
    }

    /**
     * Writes TO, USING and WITH CHECK, each when given.
     *
     * @param list<RoleSpec> $roles
     */
    public function write(Output $out, array $roles, ?Scalar $using, ?Scalar $check): void
    {
        if ($roles !== []) {
            $out->keyword('TO')->list($roles);
        }
        if ($using !== null) {
            $out->keyword('USING')->symbol('(')->node($using)->symbol(')');
        }
        if ($check !== null) {
            $out->keyword('WITH', 'CHECK')->symbol('(')->node($check)->symbol(')');
        }
    }
}
