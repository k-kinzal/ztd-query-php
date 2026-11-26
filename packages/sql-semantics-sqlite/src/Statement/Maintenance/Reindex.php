<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Maintenance;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;

/**
 * A request to rebuild indexes.
 *
 * Rule: SQLITE-REINDEX-001. Without a name every index of every attached
 * schema is rebuilt. An unqualified name is a collation name when a collation
 * with that name is registered on the connection, otherwise a table or an
 * index; a qualified name is a table or an index. Collations and indexes are
 * not part of a declaration context, so the name is kept unresolved and no
 * relation fact or diagnostic is recorded.
 * Source: https://sqlite.org/lang_reindex.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Reindex implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName|null $target The collation, table or index name; null rebuilds every index
     */
    public function __construct(public readonly ?QualifiedName $target = null)
    {
        Check::input($target?->catalog === null, 'A reindex target has at most a schema qualifier.');
    }

    /**
     * Derives nothing: the kind of object the name denotes is connection state.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('REINDEX');
        if ($this->target !== null) {
            (new ObjectNames())->write($out, $this->target);
        }
    }
}
