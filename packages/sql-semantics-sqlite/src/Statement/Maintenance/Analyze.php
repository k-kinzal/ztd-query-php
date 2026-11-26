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
 * A request to gather the statistics the query planner uses.
 *
 * Rule: SQLITE-ANALYZE-001. Without a name every attached schema is analyzed.
 * An unqualified name is a schema name when a schema with that name is
 * attached, otherwise a table or an index; a qualified name is a table or an
 * index. Attached schemas and indexes are not part of a declaration context,
 * so the name is kept unresolved and no relation fact or diagnostic is
 * recorded. The statistics tables it writes are internal.
 * Source: https://sqlite.org/lang_analyze.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Analyze implements Statement
{
    use Snapshot;

    /**
     * @param QualifiedName|null $target The schema, table or index name; null analyzes every attached schema
     */
    public function __construct(public readonly ?QualifiedName $target = null)
    {
        Check::input($target?->catalog === null, 'An analysis target has at most a schema qualifier.');
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
        $out->keyword('ANALYZE');
        if ($this->target !== null) {
            (new ObjectNames())->write($out, $this->target);
        }
    }
}
