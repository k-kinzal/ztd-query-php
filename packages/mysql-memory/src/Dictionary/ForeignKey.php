<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;

/**
 * A foreign key of a table: the columns of the child table that reference a key of a parent table, and what the server does when the parent row changes.
 *
 * A child row whose columns are all not NULL must match a parent row in the referenced columns.
 * A change of a parent row a child row matches is refused (RESTRICT and NO ACTION, the default),
 * or carried to the child rows (CASCADE), or sets their columns to NULL (SET NULL). InnoDB
 * refuses SET DEFAULT when the key is declared.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 *
 * @visibility MySqlMemory
 */
final class ForeignKey
{
    /**
     * @param string $name The constraint name
     * @param list<int> $columns The positions of the referencing columns in the child table
     * @param string $parentSchema The database of the parent table
     * @param string $parentTable The name of the parent table
     * @param list<string> $parentColumns The names of the referenced columns, as written
     * @param ReferenceOption|null $onDelete The action ON DELETE names, or null when none is written
     * @param ReferenceOption|null $onUpdate The action ON UPDATE names, or null when none is written
     * @param bool $generatedName Whether the server named the constraint
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly string $parentSchema,
        public readonly string $parentTable,
        public readonly array $parentColumns,
        public readonly ?ReferenceOption $onDelete = null,
        public readonly ?ReferenceOption $onUpdate = null,
        public readonly bool $generatedName = false,
    ) {
    }

    /**
     * Writes the key as SHOW CREATE TABLE and the errors of a violated key write it: its columns, the referenced table, with its database when that is not the database of the child table, and its actions but NO ACTION.
     *
     * MySQL 5.6 and 5.7 write no RESTRICT either, and ER_TRUNCATE_ILLEGAL_FK writes the referenced
     * table with its database and no action (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param bool $legacy Whether the text is that of MySQL 5.6 or 5.7
     * @param bool $truncated Whether the text is that of a refused TRUNCATE in MySQL 5.6 or 5.7
     */
    public function text(TableDefinition $child, bool $legacy = false, bool $truncated = false): string
    {
        $name = static fn (string $name): string => '`' . str_replace('`', '``', $name) . '`';
        $columns = implode(', ', array_map(static fn (int $position): string => $name($child->columns[$position]->name), $this->columns));
        $parent = ($this->parentSchema === $child->schema && !$truncated ? '' : $name($this->parentSchema) . '.') . $name($this->parentTable);
        $text = 'CONSTRAINT ' . $name($this->name) . ' FOREIGN KEY (' . $columns . ') REFERENCES ' . $parent . ' (' . implode(', ', array_map($name, $this->parentColumns)) . ')';
        $hidden = $legacy ? [null, ReferenceOption::NoAction, ReferenceOption::Restrict] : [null, ReferenceOption::NoAction];
        if (!$truncated && !in_array($this->onDelete, $hidden, true)) {
            $text .= ' ON DELETE ' . $this->onDelete->value;
        }
        if (!$truncated && !in_array($this->onUpdate, $hidden, true)) {
            $text .= ' ON UPDATE ' . $this->onUpdate->value;
        }

        return $text;
    }

    /**
     * Answers the same key referencing a table under another name, as a rename of the referenced table leaves it.
     */
    public function retargeted(string $schema, string $table): self
    {
        return new self($this->name, $this->columns, $schema, $table, $this->parentColumns, $this->onDelete, $this->onUpdate, $this->generatedName);
    }

    /**
     * Tells whether the key references a table.
     */
    public function references(string $schema, string $table): bool
    {
        return $this->parentSchema === $schema && $this->parentTable === $table;
    }
}
