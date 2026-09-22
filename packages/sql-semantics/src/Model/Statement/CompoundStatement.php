<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundQuery;

/**
 * A set operation with ordered query branches and ordinal result compatibility.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT 1 UNION ALL SELECT 2');
 *     $statement->setOperator // => 'UNION ALL'
 *
 * @visibility public
 */
final class CompoundStatement extends BoundQuery
{
    /**
     * Replaces one set operand and recomputes result width and common types.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withBranch(int $ordinal, BoundQuery $branch): self
    {
        if (!isset($this->branches[$ordinal])) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The set operand does not exist.');
        }
        return $this->component($this->branches[$ordinal]->source, \SqlSemantics\Model\Sql\Parts::query($branch, $this->context()->schema()->dialect));
    }
}
