<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

/**
 * Converts a table to the default character set of its database (CONVERT TO CHARACTER SET DEFAULT).
 * @visibility public
 * @example Reading a conversion to the database default
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t CONVERT TO CHARACTER SET DEFAULT');
 *     $statement->alterations[0]->characterSet // => \SqlSemantics\Model\Definition\MySqlTable\Table\InheritedCharacterSet::Database
 */
enum InheritedCharacterSet
{
    case Database;
}
