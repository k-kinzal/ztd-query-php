<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Maintenance;

use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Collects statistics for a named database object, or all eligible objects.
 * @example Reconstructing a database-wide maintenance request
 *     (new \SqlSemantics\Statement\Maintenance\Analyze())->toString() // => 'ANALYZE'
 * @visibility public
 */
final class Analyze implements Operation
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
        return 'ANALYZE' . ($this->target === null ? '' : ' ' . $this->target->toString());
    }
}
