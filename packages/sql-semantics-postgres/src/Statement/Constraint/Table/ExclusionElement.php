<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\IndexElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * One element of an exclusion constraint: a key and the operator no two rows may satisfy together.
 *
 * Mirrors one pair of the `exclusions` list of `CONSTR_EXCLUSION`. The
 * operator is written as an operator or as `OPERATOR(...)`, which the
 * operator name keeps.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-EXCLUDE.
 *
 * @visibility public
 * @example Reading the operator of an exclusion element
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (c circle, EXCLUDE USING gist (c WITH &&))');
 *     $create->statement->definition->elements[1]->elements[0]->operator->name->value // => '&&'
 */
final class ExclusionElement implements Clause
{
    use Snapshot;

    /**
     * @param IndexElement $element The key
     * @param OperatorName $operator The operator
     */
    public function __construct(public readonly IndexElement $element, public readonly OperatorName $operator)
    {
    }

    /**
     * Derives the key.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->element->deriveClause($derivation, $environment);
    }

    /**
     * Writes the key, WITH and the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->element)->keyword('WITH')->node($this->operator);
    }
}
