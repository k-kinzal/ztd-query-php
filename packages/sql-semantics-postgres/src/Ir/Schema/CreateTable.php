<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * The declaration of one ordinary persistent native table.
 *
 * @visibility public
 * @example Reading the target schema
 *     $schema = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('app');
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('accounts');
 *     $table = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName($schema, $name);
 *     $column = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableColumn($name, \SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage::Standard);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable($table, [$column]))->target->schema->value // => 'app'
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
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly ?PrimaryKey $primaryKey = null,
    ) {
        $checked = Check::listOf($columns, TableColumn::class, 'CREATE TABLE needs a nonempty ordered column list.', 1);
        Check::input(count($checked) <= 128, 'The declared table can have at most 128 columns.');
        $seen = [];
        foreach ($checked as $column) {
            $key = $column->name->value;
            Check::input(!isset($seen[$key]), 'CREATE TABLE cannot repeat a column name.');
            $seen[$key] = true;
        }
        if ($primaryKey !== null) {
            Check::input($primaryKey->name?->value !== $target->table->value, 'A primary key index cannot have its table name.');
            foreach ([...$primaryKey->keys, ...$primaryKey->included] as $column) {
                Check::input(isset($seen[$column->value]), 'A primary key reference must select a declared column.');
            }
            $required = [];
            foreach ($primaryKey->keys as $column) {
                $required[$column->value] = true;
            }
            foreach ($checked as $position => $column) {
                if ($column->nullable && isset($required[$column->name->value])) {
                    $checked[$position] = new TableColumn($column->name, $column->storage, false, $column->default);
                }
            }
        }
        $this->columns = $checked;
    }

    /**
     * Identifies the native release and fixed storage role.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native/heap';
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
            $columns[] = $this->primaryKey->toString();
        }

        return 'CREATE TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->target->quoted()
            . ' (' . implode(', ', $columns) . ') USING heap';
    }
}
