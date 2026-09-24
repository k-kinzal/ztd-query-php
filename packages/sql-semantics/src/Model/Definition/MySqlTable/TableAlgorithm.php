<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable;

/**
 * The algorithm a MySQL ALTER TABLE requests; INSTANT exists from MySQL 8.0.
 * @visibility public
 * @example Reading the requested algorithm
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALGORITHM = INSTANT, ADD COLUMN n INT');
 *     $statement->algorithm // => \SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm::Instant
 */
enum TableAlgorithm: string
{
    case Default = 'DEFAULT';
    case Instant = 'INSTANT';
    case Inplace = 'INPLACE';
    case Copy = 'COPY';
}
