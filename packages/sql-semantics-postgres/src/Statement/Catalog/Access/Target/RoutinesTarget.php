<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\RoutineSignature;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * Functions, procedures or routines named as the objects of a GRANT or REVOKE.
 *
 * Each is named with or without its argument types. Routines are not
 * declarations a context holds, so the signatures are not resolved; the
 * expressions inside their argument types are derived.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the function of a grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT EXECUTE ON FUNCTION app.total(int4) TO joe');
 *     $operation->statement->target->signatures[0]->name->last()->value // => 'total'
 */
final class RoutinesTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * @var non-empty-list<RoutineSignature> The signatures in the order written
     */
    public readonly array $signatures;

    /**
     * @param PrivilegeObjectKind $object The kind: functions, procedures or routines
     * @param list<RoutineSignature> $signatures The signatures in the order written, at least one
     */
    public function __construct(public readonly PrivilegeObjectKind $object, array $signatures)
    {
        Check::input(in_array($object, [PrivilegeObjectKind::Function, PrivilegeObjectKind::Procedure, PrivilegeObjectKind::Routine], true), 'Signatures are functions, procedures or routines.');
        $this->signatures = Check::listOf($signatures, RoutineSignature::class, 'A grant names at least one routine.', 1);
    }

    /**
     * Answers the kind of the objects.
     */
    public function object(): PrivilegeObjectKind
    {
        return $this->object;
    }

    /**
     * Derives the expressions inside the argument types.
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void
    {
        foreach ($this->signatures as $signature) {
            $signature->deriveClause($derivation, $derivation->environment());
        }
    }

    /**
     * Writes the kind and the signatures.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->object->value)->list($this->signatures);
    }
}
