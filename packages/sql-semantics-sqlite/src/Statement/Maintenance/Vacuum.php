<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Maintenance;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;

/**
 * A request to rebuild a database file, optionally into a new file.
 *
 * Rule: SQLITE-VACUUM-001. The optional name is a schema name; without it the
 * main schema is rebuilt. The INTO operand is an expression that yields the
 * file name; it is derived at a position that sees no relation, so a column
 * name in it is a missing column.
 * Source: https://sqlite.org/lang_vacuum.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the schema and the target of a vacuum
 *     $vacuum = (new \SqlSemantics\Platform\Sqlite\Semantics())->inspect("VACUUM main INTO 'backup.db'");
 *     [$vacuum->statement->schema?->value, $vacuum->statement->into !== null] // => ['main', true]
 */
final class Vacuum implements Statement
{
    use Snapshot;

    /**
     * @param Name|null $schema The schema to rebuild; null is the main schema
     * @param Scalar|null $into The expression that names the file to write the rebuilt database to
     */
    public function __construct(public readonly ?Name $schema = null, public readonly ?Scalar $into = null)
    {
    }

    /**
     * Derives the file name expression where no relation is visible.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->into !== null) {
            $derivation->scalar($this->into, $derivation->environment());
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('VACUUM');
        if ($this->schema !== null) {
            $out->name($this->schema, NameUse::Qualifier);
        }
        if ($this->into !== null) {
            $out->keyword('INTO')->node($this->into);
        }
    }
}
