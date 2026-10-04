<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A FOREIGN KEY constraint: the child columns and the reference to the parent table.
 *
 * Rule: MYSQL-FOREIGN-KEY-001. The grammar admits prefix lengths and
 * directions on the child columns; they are kept as written. The name after
 * FOREIGN KEY names the index the server creates for the constraint. The
 * parent table is resolved by MYSQL-REFERENCES-001. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a foreign key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE c (p INT, CONSTRAINT fk FOREIGN KEY (p) REFERENCES p (id))');
 *     [$create->statement->elements[1]->constraint->name->column->value, $create->statement->elements[1]->references->table->name->value] // => ['fk', 'p']
 */
final class ForeignKey implements TableElement
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnPart> The child columns in order
     */
    public readonly array $columns;

    /**
     * @param list<ColumnPart> $columns The child columns in order; at least one
     * @param References $references The parent table and columns
     * @param ColumnName|null $name The index name written after FOREIGN KEY
     * @param ConstraintName|null $constraint The CONSTRAINT clause, when written
     */
    public function __construct(array $columns, public readonly References $references, public readonly ?ColumnName $name = null, public readonly ?ConstraintName $constraint = null)
    {
        $this->columns = Check::listOf($columns, ColumnPart::class, 'A foreign key has at least one column.', 1);
    }

    /**
     * Derives nothing: the child columns are names, and the parent table is resolved by the statement.
     */
    public function deriveElement(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the constraint, the columns and the reference.
     */
    public function render(Output $out): void
    {
        $out->node($this->constraint)->keyword('FOREIGN', 'KEY')->node($this->name)->symbol('(')->list($this->columns)->symbol(')')->node($this->references);
    }
}
