<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A COLLATE column constraint: the default collating sequence of the column.
 *
 * Rule: SQLITE-COLUMN-COLLATE-001. The name is kept as written. Collating
 * sequences are registered on a connection and are not part of a declaration
 * context, so an unknown name is not a diagnostic of the model.
 * Source: https://sqlite.org/datatype3.html#collation. Status: Implemented.
 *
 * @visibility public
 * @example Reading the collation of a column
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a TEXT COLLATE NOCASE)');
 *     $create->statement->columns[0]->constraints[0]->name->value // => 'NOCASE'
 */
final class Collation implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Name $name The collating sequence
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives nothing: collating sequences are not declared in a context.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('COLLATE')->name($this->name, NameUse::Label);
    }
}
