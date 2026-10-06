<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `RESET parameter`, `RESET ALL` or a keyword form: a request to restore the default of a configuration parameter.
 *
 * Rule: PG-RESET-001. Mirrors PostgreSQL's `VariableSetStmt` of kind
 * `VAR_RESET` or `VAR_RESET_ALL`. The same structure is the RESET clause of
 * a routine, a role, a database and ALTER SYSTEM. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-reset.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a RESET of a keyword form
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('RESET TIME ZONE');
 *     [$operation->statement->parameter->parameter(), $operation->toString()] // => ['timezone', 'RESET TIME ZONE']
 */
final class Reset implements Statement
{
    use Snapshot;

    /**
     * @param ParameterName|SpecialParameter $parameter The parameter, or the keywords that name one or all
     */
    public function __construct(public readonly ParameterName|SpecialParameter $parameter)
    {
    }

    /**
     * Derives nothing: a configuration parameter is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('RESET');
        if ($this->parameter instanceof SpecialParameter) {
            $out->keyword(...explode(' ', $this->parameter->value));

            return;
        }
        $out->node($this->parameter);
    }
}
