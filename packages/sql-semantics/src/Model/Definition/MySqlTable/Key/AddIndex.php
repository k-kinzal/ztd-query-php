<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Key;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\IndexDefinition;

/**
 * Adds a plain, FULLTEXT, or SPATIAL index to the altered table.
 * @visibility public
 * @example Reading the added index
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ADD INDEX ix (id DESC)');
 *     $statement->alterations[0]->index->name // => 'ix'
 */
final class AddIndex implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly IndexDefinition $index)
    {
        if ($index->unique) {
            throw new InvalidStructure('A unique key is added as an integrity constraint.');
        }
    }
}
