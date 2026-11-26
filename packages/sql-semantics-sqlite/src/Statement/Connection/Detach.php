<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Connection;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;

/**
 * A request to remove an attached database from the connection.
 *
 * Rule: SQLITE-DETACH-001. The schema name is an expression evaluated where no
 * relation is visible; an operand that is a single identifier is the text of
 * its name (NameOperand). The optional DATABASE keyword is not written.
 * Whether the schema is attached is connection state and no fact of the model.
 * Source: https://sqlite.org/lang_detach.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Detach implements Statement
{
    use Snapshot;

    /**
     * @param Scalar $schema The expression that names the schema
     */
    public function __construct(public readonly Scalar $schema)
    {
    }

    /**
     * Derives the operand where no relation is visible.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->scalar($this->schema, $derivation->environment());
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DETACH')->node($this->schema);
    }
}
