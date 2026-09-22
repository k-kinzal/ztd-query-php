<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition as Statement;

/**
 * Serializes the mandatory table ownership and concurrency of drop operations.
 * @visibility SqlSemantics
 */
final class OwnedDrops
{
    /**
     * Writes operands directly from the concrete drop form.
     */
    public static function write(Statement\DropTableIndexStatement|Statement\DropIndexConcurrentlyStatement|Statement\DropTableTriggerStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        return match (true) {
            $statement instanceof Statement\DropTableIndexStatement => new Tree('drop-index', [Build::keyword('DROP INDEX'), Build::identifier([$statement->name], $dialect), Build::keyword('ON'), Build::identifier($statement->table->parts, $dialect), Build::keyword('ALGORITHM = ' . $statement->algorithm->value . ' LOCK = ' . $statement->lock->value)]),
            $statement instanceof Statement\DropIndexConcurrentlyStatement => new Tree('drop-index', [Build::keyword('DROP INDEX CONCURRENTLY' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier($statement->name->parts, $dialect)]),
            $statement instanceof Statement\DropTableTriggerStatement => new Tree('drop-trigger', [Build::keyword('DROP TRIGGER' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier([$statement->name], $dialect), Build::keyword('ON'), Build::identifier($statement->table->parts, $dialect), Build::keyword($statement->behavior->value)]),
        };
    }
}
