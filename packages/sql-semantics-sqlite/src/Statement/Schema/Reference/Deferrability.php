<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;

/**
 * A DEFERRABLE or NOT DEFERRABLE clause of a foreign key.
 *
 * Rule: SQLITE-FK-DEFERRABLE-001. A foreign key is checked at the end of the
 * transaction only when the clause is `DEFERRABLE INITIALLY DEFERRED`; every
 * other form, `NOT DEFERRABLE INITIALLY DEFERRED` included, is checked at the
 * end of each statement. Written as a column constraint, the clause applies
 * to the foreign key defined last before it in the table definition and has
 * no effect when there is none.
 * Source: https://sqlite.org/foreignkeys.html#fk_deferred. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Deferrability implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param bool $deferrable Whether DEFERRABLE is written without NOT
     * @param InitialMode|null $initially The word written after INITIALLY
     */
    public function __construct(public readonly bool $deferrable, public readonly ?InitialMode $initially = null)
    {
    }

    /**
     * Tells whether the foreign key is checked at the end of the transaction.
     */
    public function deferred(): bool
    {
        return $this->deferrable && $this->initially === InitialMode::Deferred;
    }

    /**
     * Derives nothing: the clause has no operand.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        if (!$this->deferrable) {
            $out->keyword('NOT');
        }
        $out->keyword('DEFERRABLE');
        if ($this->initially !== null) {
            $out->keyword('INITIALLY', $this->initially->value);
        }
    }
}
