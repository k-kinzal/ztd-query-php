<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\ColumnSpecification;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * One column of a table definition: its name and its specification.
 *
 * Rule: MYSQL-COLUMN-DEFINITION-001. MySQL 5.x lets a table and database
 * qualify the name, which the server requires to name the table of the
 * statement; the qualifier is kept as written. The expressions inside the
 * specification are derived at the position of the table definition
 * (MYSQL-DEFINITION-SCOPE-001). Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/5.7/en/identifier-qualifiers.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a column definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (id BIGINT UNSIGNED NOT NULL)');
 *     [$create->statement->elements[0]->name->column->value, $create->statement->elements[0]->specification->dataType()->name()] // => ['id', 'BIGINT']
 */
final class ColumnDefinition implements TableElement
{
    use Snapshot;

    /**
     * @param ColumnName $name The column name, with the qualifier MySQL 5.x allows
     * @param ColumnSpecification $specification The type, attributes, generation expression and reference
     */
    public function __construct(public readonly ColumnName $name, public readonly ColumnSpecification $specification)
    {
    }

    /**
     * Derives the expressions of the specification inside the table definition.
     */
    public function deriveElement(Derivation $derivation, Environment $scope): void
    {
        $this->specification->deriveSpecification($derivation, $scope);
    }

    /**
     * Writes the name and the specification.
     */
    public function render(Output $out): void
    {
        $out->node($this->name)->node($this->specification);
    }
}
