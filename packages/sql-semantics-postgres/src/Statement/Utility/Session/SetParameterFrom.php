<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * `SET [LOCAL] parameter TO DEFAULT` or `SET [LOCAL] parameter FROM CURRENT`: a parameter set without a written value.
 *
 * Rule: PG-SET-002. Mirrors PostgreSQL's `VariableSetStmt` of kind
 * `VAR_SET_DEFAULT` or `VAR_SET_CURRENT`. `TO DEFAULT` and `= DEFAULT` are
 * the same request. Facts: none.
 * Source: https://www.postgresql.org/docs/17/sql-set.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
