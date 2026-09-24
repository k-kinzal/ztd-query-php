<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation\Joining;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A same-named input pair and the merged column exposed by USING or NATURAL.
 *
 * @visibility public
 * @example Reading a merged join column
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT id FROM t AS a JOIN t AS b USING (id)');
 *     $column = $query->from->columns[0];
 *     $column instanceof \SqlSemantics\Model\Relation\Joining\SharedColumn // => true
 *     $column->name // => 'id'
 *     $column->left->columnBinding()->relationId // => 'r0'
 *     $column->right->columnBinding()->relationId // => 'r1'
 */
final class SharedColumn
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly Expression $left, public readonly Expression $right, public readonly Expression $output)
    {
        if ($name === '') {
            throw new InvalidStructure('A shared join column requires a name.');
        }
        if ($left->type->dialect !== $right->type->dialect || $left->type->dialect !== $output->type->dialect) {
            throw new InvalidStructure('A shared join column must use one SQL dialect.');
        }
    }
}
