<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Maintenance;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Rebuilds indexes selected by a collation, table, or index name, or all indexes.
 * @example Reconstructing a database-wide maintenance request
 *     (new \SqlSemantics\Statement\Maintenance\Reindex())->toString() // => 'REINDEX'
 * @visibility public
 */
final class Reindex implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Describes the requested operation without applying it to the declaration context.
     */
    public function __construct(public readonly ?QualifiedName $target = null)
    {
    }

    /**
     * Reconstructs SQL from the operation target and semantic options.
     */
    public function toString(): string
    {
        return 'REINDEX' . ($this->target === null ? '' : ' ' . $this->target->toString());
    }
}
