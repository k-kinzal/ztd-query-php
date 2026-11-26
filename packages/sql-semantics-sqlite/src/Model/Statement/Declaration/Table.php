<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Declaration;

use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

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
     * @param ColumnRemoval|null $columnRemoval Local drop restrictions derived from the definition, when known
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly LanguageProfile $profile,
        array $columns,
        array $implicit = [],
        public readonly bool $complete = true,
        public readonly RelationKind $kind = RelationKind::BaseTable,
        public readonly ?ColumnRemoval $columnRemoval = null,
    ) {
        $this->columns = Check::listOf($columns, Column::class, 'Table columns are an ordered list of column declarations.');
        $this->implicit = Check::listOf($implicit, ImplicitColumn::class, 'Implicit table columns are a list of implicit column declarations.');
        Check::input(
            $columnRemoval === null || ($kind === RelationKind::BaseTable && $complete && $columnRemoval->width === count($this->columns)),
            'Column removal facts must match a complete ordinary table heading.',
        );
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
