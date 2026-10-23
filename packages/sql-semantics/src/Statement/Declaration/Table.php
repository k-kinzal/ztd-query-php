<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A relation declaration supplied to an analysis context: a table or a view with its columns.
 *
 * The object is the identity of the declaration. Two equal-looking tables are
 * two declarations, and a context that holds both keeps the conflict.
 *
 * @visibility public
 * @example Reading the declaration a CREATE TABLE provides
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE users (id INTEGER, name TEXT)')->declarations()[0];
 *     [$table->name->name->value, count($table->columns)] // => ['users', 2]
 */
final class Table
{
    use Snapshot;

    /**
     * @var list<Column> The declared columns in declaration order
     */
    public readonly array $columns;

    /**
     * @var list<ImplicitColumn> The columns found by name without being declared
     */
    public readonly array $implicit;

    /**
     * @var list<Key> The primary key and the unique keys, the primary key first
     */
    public readonly array $keys;

    /**
     * @var list<Name>|null The partitions and subpartitions a statement can select by name; empty when the table is not partitioned, null when unknown
     */
    public readonly ?array $partitions;

    /**
     * @param QualifiedName $name The declared name
     * @param LanguageProfile $profile The language profile the declaration was read under
     * @param list<Column> $columns The declared columns in declaration order
     * @param list<ImplicitColumn> $implicit The columns found by name without being declared
     * @param bool $complete Whether the column list is the complete member list of the relation
     * @param RelationKind $kind Whether the relation is a base table, a view, or another kind of relation
     * @param list<Key> $keys The primary key and the unique keys over the columns, the primary key first
     * @param list<Name>|null $partitions The partitions and subpartitions a statement can select by name; empty when the table is not partitioned, null when the declaration does not say
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly LanguageProfile $profile,
        array $columns,
        array $implicit = [],
        public readonly bool $complete = true,
        public readonly RelationKind $kind = RelationKind::BaseTable,
        array $keys = [],
        ?array $partitions = null,
    ) {
        $this->columns = Check::listOf($columns, Column::class, 'Table columns are an ordered list of column declarations.');
        $this->implicit = Check::listOf($implicit, ImplicitColumn::class, 'Implicit table columns are a list of implicit column declarations.');
        $this->keys = Check::listOf($keys, Key::class, 'Table keys are a list of key declarations.');
        $this->partitions = $partitions === null ? null : Check::listOf($partitions, Name::class, 'Partitions are named.');
    }

    /**
     * Finds the declared columns a name denotes, falling back to the implicit columns.
     *
     * More than one result means the declaration itself repeats the name.
     *
     * @return list<Column>
     */
    public function matchingColumns(string $name, Comparison $comparison): array
    {
        $declared = [];
        foreach ($this->columns as $column) {
            if ($comparison->equal($column->name->value, $name)) {
                $declared[] = $column;
            }
        }
        if ($declared !== []) {
            return $declared;
        }
        foreach ($this->implicit as $implicit) {
            foreach ($implicit->names as $candidate) {
                if ($comparison->equal($candidate->value, $name)) {
                    return [$implicit->column];
                }
            }
        }

        return [];
    }
}
