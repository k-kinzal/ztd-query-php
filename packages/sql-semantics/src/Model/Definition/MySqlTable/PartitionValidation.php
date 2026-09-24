<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable;

/**
 * Whether rows are checked against partition definitions while a partitioned table changes (MySQL 5.7 and later).
 * @visibility public
 * @example Reading the requested validation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)', 'CREATE TABLE u(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t EXCHANGE PARTITION p WITH TABLE u WITHOUT VALIDATION');
 *     $statement->validation // => \SqlSemantics\Model\Definition\MySqlTable\PartitionValidation::Without
 */
enum PartitionValidation: string
{
    case With = 'WITH VALIDATION';
    case Without = 'WITHOUT VALIDATION';
}
