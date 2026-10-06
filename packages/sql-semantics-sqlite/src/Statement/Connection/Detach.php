<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Connection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove an attached database from the connection.
 *
 * Rule: SQLITE-DETACH-001. The schema name is an expression evaluated where no
 * relation is visible; an operand that is a single identifier is the text of
 * its name (NameOperand). The optional DATABASE keyword is not written.
 * Whether the schema is attached is connection state and no fact of the model.
 * Source: https://sqlite.org/lang_detach.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the schema a detach request names
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('DETACH DATABASE aux')->statement->schema->name->value // => 'aux'
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
