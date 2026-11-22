<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;

/**
 * A COLLATE column constraint: the default collating sequence of the column.
 *
 * Rule: SQLITE-COLUMN-COLLATE-001. The name is kept as written. Collating
 * sequences are registered on a connection and are not part of a declaration
 * context, so an unknown name is not a diagnostic of the model.
 * Source: https://sqlite.org/datatype3.html#collation. Status: Implemented.
 *
 * @visibility SqlSemantics
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
