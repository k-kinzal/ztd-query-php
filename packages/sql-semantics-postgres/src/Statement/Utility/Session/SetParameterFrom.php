<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SET [LOCAL] parameter TO DEFAULT` or `SET [LOCAL] parameter FROM CURRENT`: a parameter set without a written value.
 *
 * Rule: PG-SET-002. Mirrors PostgreSQL's `VariableSetStmt` of kind
 * `VAR_SET_DEFAULT` or `VAR_SET_CURRENT`. `TO DEFAULT` and `= DEFAULT` are
 * the same request. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-set.html. Status: Implemented.
 *
 * @visibility public
 * @example Restoring a default
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET work_mem = DEFAULT');
 *     [$operation->statement->source, $operation->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterSource::Default, 'SET work_mem TO DEFAULT']
 */
final class SetParameterFrom implements Statement
{
    use Snapshot;

    /**
     * @param ParameterName $parameter The parameter
     * @param ParameterSource $source Where the value comes from
     * @param bool $local Whether the change lasts for the current transaction only
     */
    public function __construct(public readonly ParameterName $parameter, public readonly ParameterSource $source, public readonly bool $local = false)
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
        $out->keyword('SET');
        if ($this->local) {
            $out->keyword('LOCAL');
        }
        $out->node($this->parameter);
        match ($this->source) {
            ParameterSource::Default => $out->keyword('TO', 'DEFAULT'),
            ParameterSource::Current => $out->keyword('FROM', 'CURRENT'),
        };
    }
}
