<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Constraint;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\References;

/**
 * Refuses to drop an index a foreign key needs (ER_DROP_INDEX_FK).
 *
 * A foreign key of the table needs an index that starts with its columns, and a foreign key that
 * references the table needs a unique key of the referenced columns; an index that remains can
 * take the place of the one dropped (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 *
 * @visibility MySqlMemory
 */
final class KeyNeeds
{
    /**
     * @param Session $session The session changing the table
     * @param Context $context The statement
     */
    public function __construct(public readonly Session $session, public readonly Context $context)
    {
    }

    /**
     * Refuses the change when an index it drops is one a foreign key needs.
     *
     * @param list<string> $dropped The names of the indexes the change drops
     *
     * @throws SqlError When a foreign key needs one
     */
    public function dropped(StoredTable $table, TableDefinition $definition, array $dropped): void
    {
        $old = $table->definition;
        foreach ($dropped as $name) {
            $key = $this->named($old, $name);
            if ($key === null) {
                continue;
            }
            foreach ($old->foreignKeys as $foreign) {
                if (ForeignKeys::starts($key, $foreign->columns) && !$this->covered($old, $foreign->columns, $dropped)) {
                    throw ConstraintError::DropIndexForeignKey->error($key->name);
                }
            }
            foreach ((new References($this->session, $this->context))->children($table) as [, $foreign]) {
                $positions = array_map(static fn (string $column): ?int => $old->position($column), $foreign->parentColumns);
                if ($key->unique() && $key->columns === $positions && !$this->unique($definition, $foreign->parentColumns)) {
                    throw ConstraintError::DropIndexForeignKey->error($key->name);
                }
            }
        }
    }

    /**
     * Makes the foreign keys of other tables that reference a changed table follow its columns to their new names, refusing a change that drops one (ER_FK_COLUMN_CANNOT_DROP_CHILD).
     *
     * @throws SqlError When the change drops a referenced column
     */
    public function followed(StoredTable $table, \MySqlMemory\Command\Definition\TableLayout $layout): void
    {
        $changes = [];
        foreach ((new References($this->session, $this->context))->children($table) as [$child, $key]) {
            if ($child === $table) {
                continue;
            }
            $columns = [];
            foreach ($key->parentColumns as $column) {
                $name = $layout->renamed($column);
                if ($name === false) {
                    throw ConstraintError::DropReferencedColumn->error($column, $key->name, $child->definition->name);
                }
                $columns[] = $name ?? $column;
            }
            $changes[] = [$child, $key, $columns];
        }
        foreach ($changes as [$child, $key, $columns]) {
            $keys = array_map(static fn ($kept) => $kept === $key ? new \MySqlMemory\Dictionary\ForeignKey($key->name, $key->columns, $key->parentSchema, $key->parentTable, $columns, $key->onDelete, $key->onUpdate, $key->generatedName) : $kept, $child->definition->foreignKeys);
            $child->definition = $child->definition->withConstraints($child->definition->checks, $keys);
        }
    }

    /**
     * Answers the key of a definition by name, compared without regard to case, or null.
     */
    public function named(TableDefinition $definition, string $name): ?Key
    {
        foreach ($definition->keys as $key) {
            if (strcasecmp($key->name, $name) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Tells whether a key the change keeps starts with columns.
     *
     * @param list<int> $columns
     * @param list<string> $dropped
     */
    public function covered(TableDefinition $old, array $columns, array $dropped): bool
    {
        foreach ($old->keys as $key) {
            if (!in_array(mb_strtolower($key->name), array_map(mb_strtolower(...), $dropped), true) && ForeignKeys::starts($key, $columns)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether a definition has a unique key of columns, named in order.
     *
     * @param list<string> $columns
     */
    public function unique(TableDefinition $definition, array $columns): bool
    {
        $positions = array_map(static fn (string $column): ?int => $definition->position($column), $columns);
        foreach ($definition->keys as $key) {
            if ($key->unique() && $key->columns === $positions) {
                return true;
            }
        }

        return false;
    }
}
