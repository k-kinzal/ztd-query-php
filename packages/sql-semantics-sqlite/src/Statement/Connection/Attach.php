<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Connection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to add a database file to the connection under a schema name.
 *
 * Rule: SQLITE-ATTACH-001. The file, the schema name and the optional key are
 * expressions evaluated where no relation is visible; a column name in them
 * is a missing column, and an operand that is a single identifier is the text
 * of its name (NameOperand). The optional DATABASE keyword is not written.
 * The attached schema is connection state: the statement provides no
 * declaration and changes no context.
 * Source: https://sqlite.org/lang_attach.html. Status: Implemented.
 *
 * @visibility public
 * @example Writing an attach request without the optional keyword
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("ATTACH DATABASE 'file.db' AS aux")->toString() // => "ATTACH 'file.db' AS aux"
 */
final class Attach implements Statement
{
    use Snapshot;

    /**
     * @param Scalar $file The expression that names the database file
     * @param Scalar $schema The expression that names the schema
     * @param Scalar|null $key The expression of the encryption key, which only builds with encryption support use
     */
    public function __construct(public readonly Scalar $file, public readonly Scalar $schema, public readonly ?Scalar $key = null)
    {
    }

    /**
     * Derives each operand where no relation is visible.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->scalar($this->file, $derivation->environment());
        $derivation->scalar($this->schema, $derivation->environment());
        if ($this->key !== null) {
            $derivation->scalar($this->key, $derivation->environment());
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ATTACH')->node($this->file)->keyword('AS')->node($this->schema);
        if ($this->key !== null) {
            $out->keyword('KEY')->node($this->key);
        }
    }
}
