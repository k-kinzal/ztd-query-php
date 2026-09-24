<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

/**
 * A table option an ALTER TABLE returns to its default by writing DEFAULT, or NULL for the secondary engine.
 * @visibility public
 * @example Reading a reset option
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t PACK_KEYS = DEFAULT');
 *     $statement->alterations[0]->resets // => [\SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset::PackKeys]
 */
enum TableOptionReset: string
{
    case PackKeys = 'PACK_KEYS';
    case StatsAutoRecalc = 'STATS_AUTO_RECALC';
    case StatsPersistent = 'STATS_PERSISTENT';
    case StatsSamplePages = 'STATS_SAMPLE_PAGES';
    case SecondaryEngine = 'SECONDARY_ENGINE';
    case CharacterSet = 'CHARACTER SET';
    case Collation = 'COLLATE';
}
