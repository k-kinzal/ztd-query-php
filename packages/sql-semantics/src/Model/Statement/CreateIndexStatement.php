<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use SqlSemantics\Model\BoundStatement;

/**
 * An index declaration with ordered typed keys and an optional predicate.
 *
 * @example Reading the statement structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE INDEX ix ON t(id)');
 *     $statement->indexes[0]->keys[0]->binding->column->name // => 'id'
 *
 * @visibility public
 */
final class CreateIndexStatement extends BoundStatement
{
    /**
     * Replaces one key expression, retaining its direction, collation, and operator class.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withKey(int $ordinal, \SqlSemantics\Model\Expression $expression): self
    {
        $key = $this->indexes[0]->keys[$ordinal] ?? null;
        if ($key === null) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The index key does not exist.');
        }
        return $this->replaceExpression($key, $expression);
    }
}
