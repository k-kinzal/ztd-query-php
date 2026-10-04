<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\InvariantViolation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Statement;

/**
 * The inspection of an explained statement, as one step of a derivation.
 *
 * Rule: MYSQL-EXPLAIN-INSPECTION-001. Deriving it inspects the statement it
 * wraps (Derivation::inspected), so that EXPLAIN FOR DATABASE can run the
 * inspection under other name-search settings (Derivation::within). It is a
 * working value of one derivation, never part of a published structure, and
 * cannot be rendered. Terminates: one nested derivation.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Inspection implements Statement
{
    /**
     * @param Statement $statement The inspected statement
     */
    public function __construct(private readonly Statement $statement)
    {
    }

    /**
     * Inspects the wrapped statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->inspected($this->statement);
    }

    /**
     * Refuses to be written: an inspection is not part of a statement.
     *
     * @throws InvariantViolation Always
     */
    public function render(Output $out): void
    {
        throw new InvariantViolation('An inspection is a derivation step and has no SQL.');
    }
}
