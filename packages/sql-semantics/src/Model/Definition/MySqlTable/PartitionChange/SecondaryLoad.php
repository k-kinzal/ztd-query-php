<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\PartitionChange;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\Table\SecondaryAction;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Loads or unloads the table, or only the listed partitions, in the secondary engine (MySQL 8.0 and later).
 * @visibility public
 * @example Loading two partitions
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t SECONDARY_LOAD PARTITION (p0, p1)');
 *     [$statement->alterations[0]->action, $statement->alterations[0]->partitions] // => [\SqlSemantics\Model\Definition\MySqlTable\Table\SecondaryAction::Load, ['p0', 'p1']]
 */
final class SecondaryLoad implements TableAlteration
{
    /**
     * @param list<string> $partitions Selected partitions; empty for the whole table
     * @throws InvalidStructure
     */
    public function __construct(public readonly SecondaryAction $action, public readonly array $partitions = [])
    {
        Collections::strings($partitions);
        array_map(AlterationInvariant::name(...), $partitions);
    }
}
