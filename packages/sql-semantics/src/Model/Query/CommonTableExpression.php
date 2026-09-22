<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Statement\DeleteStatement;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Statement\UpdateStatement;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\RowShape;

/**
 * One lexical relation definition with its result aliases and materialization policy.
 * @visibility public
 * @example Reading a named relation's input
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('WITH q(id) AS (SELECT 1) SELECT id FROM q');
 *     $statement->ctes->definitions[0]->columns // => ['id']
 */
final class CommonTableExpression
{
    /**
     * @param list<string> $columns Result aliases in positional order
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $name,
        public readonly BoundQuery|InsertStatement|UpdateStatement|DeleteStatement|MergeStatement $query,
        public readonly array $columns = [],
        public readonly Materialization $materialization = Materialization::Default,
    ) {
        Collections::strings($columns);
        $dialect = $query->origin->dialect;
        if (!$query instanceof BoundQuery && $dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Data-modifying CTE definitions require PostgreSQL.');
        }
        if ($materialization !== Materialization::Default && $dialect === Dialect::MySql) {
            throw new InvalidStructure('MySQL does not have a CTE materialization clause.');
        }
        $width = RowShape::width($query);
        if ($width !== null && $columns !== [] && (count($columns) > $width || $dialect !== Dialect::PostgreSql && count($columns) !== $width)) {
            throw new InvalidStructure('CTE aliases must match the declared query result positions.');
        }
    }
}
