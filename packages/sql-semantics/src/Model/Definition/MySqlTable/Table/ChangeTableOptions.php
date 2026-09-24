<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Table;

use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Table\MySqlProperties;

/**
 * Sets the table options written in an ALTER TABLE and returns the reset ones to their defaults.
 * @visibility public
 * @example Reading changed options
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ENGINE = MyISAM COMMENT = \'archive\'');
 *     $statement->alterations[0]->options->engine // => 'MyISAM'
 *     $statement->alterations[0]->options->comment // => 'archive'
 * @example Rejecting an empty change
 *     new \SqlSemantics\Model\Definition\MySqlTable\Table\ChangeTableOptions(new \SqlSemantics\Schema\Table\MySqlProperties(), []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ChangeTableOptions implements TableAlteration
{
    /**
     * @param list<TableOptionReset> $resets Options returned to their defaults, each named once
     * @throws InvalidStructure
     */
    public function __construct(public readonly MySqlProperties $options, public readonly array $resets = [])
    {
        Collections::objects($resets, TableOptionReset::class);
        if ($options->temporary || $options->startTransaction || $options->partitioning !== null) {
            throw new InvalidStructure('Table options of an ALTER TABLE exclude TEMPORARY, START TRANSACTION, and partitioning.');
        }
        $set = array_keys(array_filter(get_object_vars($options), static fn ($value): bool => $value !== null && $value !== false));
        if ($set === [] && $resets === []) {
            throw new InvalidStructure('A table option change requires at least one option.');
        }
        if (count(array_unique(array_map(static fn (TableOptionReset $reset): string => $reset->value, $resets))) !== count($resets)) {
            throw new InvalidStructure('A table option is reset at most once.');
        }
        $properties = ['PACK_KEYS' => 'packKeys', 'STATS_AUTO_RECALC' => 'statsAutoRecalc', 'STATS_PERSISTENT' => 'statsPersistent', 'STATS_SAMPLE_PAGES' => 'statsSamplePages', 'SECONDARY_ENGINE' => 'secondaryEngine', 'CHARACTER SET' => 'characterSet', 'COLLATE' => 'collation'];
        foreach ($resets as $reset) {
            if (in_array($properties[$reset->value], $set, true)) {
                throw new InvalidStructure('A table option cannot be both set and reset.');
            }
        }
    }
}
