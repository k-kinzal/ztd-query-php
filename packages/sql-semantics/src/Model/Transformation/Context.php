<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Transformation;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Schema;

/**
 * Supplies the schema and semantic validation used by immutable statement transformations.
 *
 * @visibility SqlSemantics
 */
interface Context
{
    /**
     * Returns the snapshot against which the statement is interpreted.
     */
    public function schema(): Schema;

    /**
     * Validates a complete replacement before publishing it as a new statement.
     *
     * @template T of BoundStatement
     * @param T $previous
     * @return T
     */
    public function rebind(BoundStatement $previous, Tree $sql): BoundStatement;

}
