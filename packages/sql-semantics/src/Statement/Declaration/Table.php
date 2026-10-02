<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Identifier\Comparison;
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
     * @param QualifiedName $name The declared name
     * @param LanguageProfile $profile The language profile the declaration was read under
     * @param list<Column> $columns The declared columns in declaration order
     * @param list<ImplicitColumn> $implicit The columns found by name without being declared
     * @param bool $complete Whether the column list is the complete member list of the relation
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly LanguageProfile $profile,
        array $columns,
        array $implicit = [],
        public readonly bool $complete = true,
    ) {
        $this->columns = Check::listOf($columns, Column::class, 'Table columns are an ordered list of column declarations.');
        $this->implicit = Check::listOf($implicit, ImplicitColumn::class, 'Implicit table columns are a list of implicit column declarations.');
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
