<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * ATTACH PARTITION of ALTER INDEX: makes an index a partition of the altered partitioned index.
 *
 * Mirrors `AT_AttachPartition` on an index. The index is looked up by the server; a version 1 context
 * declares no indexes.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Attaching an index
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER INDEX pi ATTACH PARTITION pi1');
 *     $statement->toString() // => 'ALTER INDEX pi ATTACH PARTITION pi1'
 */
final class AttachIndex implements AlterCommand
{
    use Snapshot;

    /**
     * @param QualifiedName $index The index
     */
    public function __construct(public readonly QualifiedName $index)
    {
    }

    /**
     * Derives nothing: the index is a catalog name.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ATTACH', 'PARTITION');
        (new Spelling())->qualified($out, $this->index);
    }
}
