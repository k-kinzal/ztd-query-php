<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration;

use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A relation declaration supplied to an analysis context: a table or a view with its columns.
 *
 * The object is the identity of the declaration. Two equal-looking tables are
 * two declarations, and a context that holds both keeps the conflict.
 *
 * @visibility SqlSemantics
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
     * @param RelationKind $kind Whether the relation is a base table, a view, or another kind of relation
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly LanguageProfile $profile,
        array $columns,
        array $implicit = [],
        public readonly bool $complete = true,
        public readonly RelationKind $kind = RelationKind::BaseTable,
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
