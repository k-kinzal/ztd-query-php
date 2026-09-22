<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;

/**
 * A MERGE with a matching condition and ordered conditional actions.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('MERGE INTO t USING t AS s ON t.id=s.id WHEN MATCHED THEN DELETE');
 *     $statement->merge->actions[0]->action // => 'delete'
 *
 * @visibility public
 */
final class MergeStatement extends BoundStatement
{
    /**
     * Sets the match condition and recomputes the complete conditional write plan.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withCondition(\SqlSemantics\Model\Expression $condition): self
    {
        if ($this->merge === null) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A MERGE requires a write plan.');
        }
        return $this->replaceExpression($this->merge->condition, $condition);
    }
}
