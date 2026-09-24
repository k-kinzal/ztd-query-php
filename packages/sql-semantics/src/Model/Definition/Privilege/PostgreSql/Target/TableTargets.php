<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql\Target;

use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\Collections;

/**
 * Tables, views, and foreign tables selected by resolved name; an unknown name is diagnosed, not rejected.
 * @visibility public
 * @example Reading the selected table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('GRANT SELECT ON t TO alice');
 *     $statement->target->tables[0]->declaration->name // => 't'
 */
final class TableTargets
{
    /**
     * @param non-empty-list<TableReference> $tables Ordered table selection
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly array $tables)
    {
        Collections::objects(Collections::nonEmpty($tables), TableReference::class);
    }
}
