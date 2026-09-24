<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

/**
 * Loads table data into, or unloads it from, the secondary engine (MySQL 8.0 and later).
 * @visibility public
 * @example Reading the secondary engine action
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t SECONDARY_UNLOAD');
 *     $statement->alterations[0]->action // => \SqlSemantics\Model\Definition\MySqlTable\Table\SecondaryAction::Unload
 */
enum SecondaryAction: string
{
    case Load = 'SECONDARY_LOAD';
    case Unload = 'SECONDARY_UNLOAD';
}
