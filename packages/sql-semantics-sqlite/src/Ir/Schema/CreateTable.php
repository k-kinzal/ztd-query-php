<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeyLayout;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\TableKeySql;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite table declaration.
 *
 * @visibility public
 * @example Reading the resolved location of a temporary table
 *     $table = (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('CREATE TEMP TABLE t (id integer NOT NULL)');
 *     $table->schema->value // => 'temp'
 */
final class CreateTable implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TableColumn>
     */
    public readonly array $columns;

    /**
     * @var list<PrimaryKey|UniqueKey>
     */
    public readonly array $keys;

    /**
     * The primary key from the ordered declaration, when present.
     */
    public readonly ?PrimaryKey $primaryKey;

    /**
     * Checks the location and ordered columns before making the declaration available.
     *
     * @param list<TableColumn> $columns The declared columns
     * @param bool $strict Whether the table applies SQLite strict typing
     * @param bool $withoutRowid Whether the table uses its primary key for storage
     * @param list<PrimaryKey|UniqueKey> $keys The checked key declaration order
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $columns,
        public readonly bool $ifNotExists = false,
        public readonly bool $strict = false,
        public readonly bool $withoutRowid = false,
        array $keys = [],
    ) {
        Check::input($schema->value !== '', 'A CREATE TABLE needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A CREATE TABLE cannot use the reserved SQLite namespace.');
        $checked = Check::listOf($columns, TableColumn::class, 'A CREATE TABLE needs a nonempty ordered column list.', 1);
        Check::input(count($checked) <= 2000, 'A CREATE TABLE can have at most 2000 columns in this profile.');
        [$orderedKeys, $primaryKey] = (new TableKeyLayout())->check($keys, $checked, $withoutRowid);
        $seen = [];
        $defaults = [];
        foreach ($checked as $column) {
            Check::input(!$strict || $column->type !== DeclaredDatatypeName::Untyped, 'A STRICT table requires every column to declare a datatype.');
            $key = $column->name->key();
            Check::input(!isset($seen[$key]), 'A CREATE TABLE cannot repeat a column name.');
            $seen[$key] = true;
            if ($column->default !== null) {
                $defaults[] = [$column->default, 0, 0];
            }
        }
        (new ExpressionScope())->check([], $defaults);
        $this->columns = $checked;
        $this->keys = $orderedKeys;
        $this->primaryKey = $primaryKey;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the declaration using its explicit schema.
     */
    public function toString(): string
    {
        $columns = (new TableKeySql())->declarations($this->columns, $this->keys);
        $options = $this->withoutRowid ? ($this->strict ? ' WITHOUT ROWID, STRICT' : ' WITHOUT ROWID')
            : ($this->strict ? ' STRICT' : '');

        return 'CREATE TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '')
            . $this->schema->quoted() . '.' . $this->table->quoted() . ' (' . implode(', ', $columns) . ')'
            . $options;
    }
}
