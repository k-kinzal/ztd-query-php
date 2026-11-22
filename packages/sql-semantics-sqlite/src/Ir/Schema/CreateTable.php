<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKeySql;
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
     * Checks the location and ordered columns before making the declaration available.
     *
     * @param list<TableColumn> $columns The declared columns
     * @param bool $strict Whether the table applies SQLite strict typing
     * @param bool $withoutRowid Whether the table uses its primary key for storage
     * @param PrimaryKey|null $primaryKey The key bound to the ordered column layout
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $table,
        array $columns,
        public readonly bool $ifNotExists = false,
        public readonly bool $strict = false,
        public readonly bool $withoutRowid = false,
        public readonly ?PrimaryKey $primaryKey = null,
    ) {
        Check::input($schema->value !== '', 'A CREATE TABLE needs a nonempty schema identifier.');
        Check::input(!str_starts_with($table->key(), 'sqlite_'), 'A CREATE TABLE cannot use the reserved SQLite namespace.');
        $checked = Check::listOf($columns, TableColumn::class, 'A CREATE TABLE needs a nonempty ordered column list.', 1);
        Check::input(count($checked) <= 2000, 'A CREATE TABLE can have at most 2000 columns in this profile.');
        Check::input(!$withoutRowid || $primaryKey !== null, 'A WITHOUT ROWID table needs a primary key.');
        foreach ($primaryKey->terms ?? [] as $term) {
            Check::input($term->slot < count($checked), 'A primary key term belongs to the declared column layout.');
        }
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
        $columns = [];
        $key = $this->primaryKey;
        $keySql = new PrimaryKeySql();
        foreach ($this->columns as $slot => $column) {
            $inline = $key !== null && $key->inline && $key->terms[0]->slot === $slot ? $keySql->inline($key) : '';
            $columns[] = $column->toString() . $inline;
        }
        if ($key !== null && !$key->inline) {
            $columns[] = $keySql->table($key, $this->columns);
        }
        $options = $this->withoutRowid ? ($this->strict ? ' WITHOUT ROWID, STRICT' : ' WITHOUT ROWID')
            : ($this->strict ? ' STRICT' : '');

        return 'CREATE TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '')
            . $this->schema->quoted() . '.' . $this->table->quoted() . ' (' . implode(', ', $columns) . ')'
            . $options;
    }
}
