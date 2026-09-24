<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\OnlyTableReference;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A published table, optionally limited to some columns and to rows matching a filter.
 * @visibility public
 * @example Reading a filtered table of a publication
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION pub FOR TABLE ONLY t (a, b) WHERE (a > 0)');
 *     $table = $statement->objects[0];
 *     $table instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublishedTable // => true
 *     $table instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublishedTable ? $table->columns : null // => ['a', 'b']
 *     $table instanceof \SqlSemantics\Model\Definition\Replication\Publication\PublishedTable ? $table->table instanceof \SqlSemantics\Model\Relation\OnlyTableReference : null // => true
 */
final class PublishedTable implements PublicationMember
{
    /**
     * @param list<string> $columns Published columns; empty publishes every column
     * @throws InvalidStructure
     */
    public function __construct(public readonly TableReference|OnlyTableReference $table, public readonly array $columns = [], public readonly ?Expression $filter = null)
    {
        Collections::strings($columns);
        if (in_array('', $columns, true) || count(array_unique($columns)) !== count($columns)) {
            throw new InvalidStructure('A publication column list names distinct nonempty columns.');
        }
        if ($filter !== null && $filter->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A publication row filter must use the PostgreSQL dialect.');
        }
    }
}
