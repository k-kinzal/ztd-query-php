<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * Configuration parameters named as the objects of a GRANT or REVOKE.
 *
 * Source: https://www.postgresql.org/docs/17/sql-grant.html.
 *
 * @visibility public
 * @example Reading the parameter of a grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SET ON PARAMETER work_mem TO joe');
 *     $operation->statement->target->parameters[0]->parameter() // => 'work_mem'
 */
final class ParametersTarget implements PrivilegeTarget
{
    use Snapshot;

    /**
     * @var non-empty-list<ParameterName> The parameters in the order written
     */
    public readonly array $parameters;

    /**
     * @param list<ParameterName> $parameters The parameters in the order written, at least one
     */
    public function __construct(array $parameters)
    {
        $this->parameters = Check::listOf($parameters, ParameterName::class, 'A grant names at least one parameter.', 1);
    }

    /**
     * Answers the kind of the objects: parameters.
     */
    public function object(): PrivilegeObjectKind
    {
        return PrivilegeObjectKind::Parameter;
    }

    /**
     * Derives nothing: a parameter name is not resolved.
     */
    public function deriveTarget(Derivation $derivation, array $privileges): void
    {
    }

    /**
     * Writes the kind and the names.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARAMETER')->list($this->parameters);
    }
}
