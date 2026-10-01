<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Inspection;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\SemanticGraph;

/**
 * Requests the virtual-machine program that would implement an operation.
 * Preparing the request can still have effects for operations such as SQLite PRAGMA.
 * @visibility public
 * @example Inspecting an operation without modeling database execution
 *     $request = new \SqlSemantics\Statement\Inspection\ExplainProgram(new \SqlSemantics\Statement\Maintenance\Reindex());
 *     $request->toString() // => 'EXPLAIN REINDEX'
 */
final class ExplainProgram implements Operation
{
    /**
     * Retains the actual operation and its declaration references.
     */
    public function __construct(public readonly Operation $operation)
    {
        assert((new SemanticGraph())->isSemanticOperation($operation), 'Inspection requires a semantic operation.');
        assert(!$operation instanceof ExplainPlan && !$operation instanceof ExplainProgram, 'An operation has at most one inspection request.');
    }

    /**
     * Reconstructs the requested inspection from its operation.
     */
    public function toString(): string
    {
        return 'EXPLAIN ' . $this->operation->toString();
    }
}
