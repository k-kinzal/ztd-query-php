<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema;

use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * The declaration of one ordinary persistent native table.
 *
 * @visibility public
 * @example Reading the target schema
 *     $schema = new \SqlSemantics\Platform\MySql\Ir\Identifier('app');
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Identifier('accounts');
 *     $table = new \SqlSemantics\Platform\MySql\Ir\Schema\TableName($schema, $name);
 *     $column = new \SqlSemantics\Platform\MySql\Ir\Schema\TableColumn($name, \SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Standard);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\CreateTable($table, [$column]))->target->schema->value // => 'app'
 */
final class CreateTable implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TableColumn> Definitions in physical output order
     */
    public readonly array $columns;

    /**
     * Closes the ordered definition with an absolute target identity.
     *
     * @param list<TableColumn> $columns The column definitions
     */
    public function __construct(
        public readonly TableName $target,
        array $columns,
        public readonly bool $ifNotExists = false,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly ?TextCollation $defaultCollation = null,
        public readonly ?PrimaryKey $primaryKey = null,
    ) {
        $checked = Check::listOf($columns, TableColumn::class, 'CREATE TABLE needs a nonempty ordered column list.', 1);
        Check::input(count($checked) <= 128, 'The declared table can have at most 128 columns.');
        $seen = [];
        $bytes = 0;
        $nullBits = 0;
        $packed = false;
        foreach ($checked as $column) {
            $key = $column->name->key();
            Check::input(!isset($seen[$key]), 'CREATE TABLE cannot repeat a column name.');
            $seen[$key] = true;
            $bytes += $column->storageBytes();
            $nullBits += (int) $column->nullable;
            $packed = $packed || $column->storage instanceof CharacterStorage;
        }
        $bitmap = intdiv($nullBits + ($packed ? 0 : 1) + 7, 8);
        Check::input($bytes + $bitmap <= 65535, 'The declared logical record cannot exceed 65535 bytes.');
        $legacyDirection = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        foreach ($primaryKey->terms ?? [] as $term) {
            $column = $checked[$term->slot] ?? null;
            Check::input(
                $column !== null && !$column->nullable && $column->storage instanceof IntegerStorage,
                'A primary key must reference an existing required signed column.'
            );
            Check::input(!$term->descending || !$legacyDirection, 'The grammar release does not store descending primary key parts.');
        }
        $this->columns = $checked;
    }

    /**
     * Identifies the native release and fixed storage role.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb';
    }

    /**
     * Writes the ordered definition at the resolved schema location.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $column->toString();
        }
        if ($this->primaryKey !== null) {
            $parts = [];
            foreach ($this->primaryKey->terms as $term) {
                $parts[] = $this->columns[$term->slot]->name->quoted() . ($term->descending ? ' DESC' : '');
            }
            $columns[] = 'PRIMARY KEY (' . implode(', ', $parts) . ')';
        }

        return 'CREATE TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->target->quoted()
            . ' (' . implode(', ', $columns) . ') ENGINE=InnoDB'
            . ($this->defaultCollation === null ? '' : ' DEFAULT CHARACTER SET utf8mb4 COLLATE ' . $this->defaultCollation->value);
    }
}
