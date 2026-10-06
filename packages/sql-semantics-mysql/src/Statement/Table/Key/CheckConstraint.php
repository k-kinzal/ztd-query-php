<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A CHECK constraint, as a table element or as a column attribute.
 *
 * Rule: MYSQL-CHECK-CONSTRAINT-001. The condition sees the columns of the
 * table (MYSQL-DEFINITION-SCOPE-001). MySQL 8.0.16 and later enforce the
 * constraint unless NOT ENFORCED is written; earlier releases parse and
 * ignore it. A table element carries its ENFORCED clause; a column attribute
 * has none of its own, because the server reads `ENFORCED` after it as a
 * separate attribute (EnforcementAttribute). Source:
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a check constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, CONSTRAINT positive CHECK (a > 0) NOT ENFORCED)');
 *     [$create->statement->elements[1]->name->name->column->value, $create->statement->elements[1]->enforced] // => ['positive', false]
 */
final class CheckConstraint implements TableElement, ColumnAttribute
{
    use Snapshot;

    /**
     * @param Scalar $condition The condition every row must not make false
     * @param ConstraintName|null $name The CONSTRAINT clause, when written
     * @param bool|null $enforced Whether ENFORCED (true) or NOT ENFORCED (false) is written after a table constraint; null when absent
     */
    public function __construct(public readonly Scalar $condition, public readonly ?ConstraintName $name = null, public readonly ?bool $enforced = null)
    {
    }

    /**
     * Derives the condition inside the table definition.
     */
    public function deriveElement(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->condition, $scope);
    }

    /**
     * Derives the condition inside the table definition.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void
    {
        $derivation->scalar($this->condition, $scope);
    }

    /**
     * Writes the constraint name, the condition and the enforcement.
     */
    public function render(Output $out): void
    {
        $out->node($this->name)->keyword('CHECK')->symbol('(')->node($this->condition)->symbol(')');
        if ($this->enforced === false) {
            $out->keyword('NOT');
        }
        if ($this->enforced !== null) {
            $out->keyword('ENFORCED');
        }
    }
}
