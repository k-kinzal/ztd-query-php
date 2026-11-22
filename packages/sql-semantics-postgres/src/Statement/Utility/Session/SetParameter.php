<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Session;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionArgument;

/**
 * `SET [LOCAL] parameter TO value [, ...]`: a request to change a configuration parameter.
 *
 * Rule: PG-SET-001. Mirrors PostgreSQL's `VariableSetStmt` of kind
 * `VAR_SET_VALUE`: the parameter, the values in order (a list-valued
 * parameter takes several) and whether the change lasts for the current
 * transaction only. `TO` and `=` are the same request, and SESSION states the
 * default duration; neither is kept. The same structure is the SET clause of
 * a routine, a role, a database and ALTER SYSTEM. Facts: none; the values
 * are words, strings and numbers the parameter interprets when the command runs.
 * Source: https://www.postgresql.org/docs/17/sql-set.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a SET without a value
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameter(new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ParameterName([new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')]), []) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class SetParameter implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<OptionArgument> The values in order
     */
    public readonly array $values;

    /**
     * @param ParameterName $parameter The parameter
     * @param list<OptionArgument> $values The values in order; at least one
     * @param bool $local Whether the change lasts for the current transaction only
     */
    public function __construct(public readonly ParameterName $parameter, array $values, public readonly bool $local = false)
    {
        $this->values = Check::listOf($values, OptionArgument::class, 'SET takes at least one value.', 1);
    }

    /**
     * Derives the values; a configuration parameter is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->values as $value) {
            $value->deriveClause($derivation, $derivation->environment());
        }
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
        $out->node($this->parameter)->keyword('TO')->list($this->values);
    }
}
