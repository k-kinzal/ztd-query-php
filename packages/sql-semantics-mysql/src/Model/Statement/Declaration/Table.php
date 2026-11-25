<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

use SqlSemantics\Platform\MySql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A relation declaration supplied to an analysis context: a table or a view with its columns.
 *
 * The object is the identity of the declaration. Two equal-looking tables are
 * two declarations. A context reports a conflict when both claim the same namespace.
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
     * @var list<IndexDeclaration>|null Physical index definitions when the names are settled
     */
    public readonly ?array $indexes;

    /**
     * @param QualifiedName $name The declared name
     * @param LanguageProfile $profile The language profile the declaration was read under
     * @param list<Column> $columns The declared columns in declaration order
     * @param list<ImplicitColumn> $implicit The columns found by name without being declared
     * @param bool $complete Whether the column list is the complete member list of the relation
     * @param RelationKind $kind Whether the relation is a base table, a view, or another kind of relation
     * @param bool $temporary Whether this declaration creates a session-temporary table
     * @param string|null $storageEngine The decoded engine of a known table declaration
     * @param bool $hasIdentityKey A prior declared primary or eligible visible unique key
     * @param list<IndexDeclaration>|null $indexes The resolved declared indexes, or an open index namespace
     */
    public function __construct(
        public readonly QualifiedName $name,
        public readonly LanguageProfile $profile,
        array $columns,
        array $implicit = [],
        public readonly bool $complete = true,
        public readonly RelationKind $kind = RelationKind::BaseTable,
        public readonly bool $temporary = false,
        public readonly ?string $storageEngine = null,
        public readonly bool $hasIdentityKey = false,
        ?array $indexes = null,
    ) {
        Check::input(!$temporary || $kind === RelationKind::BaseTable, 'Only a base table can be a temporary declaration.');
        $this->columns = Check::listOf($columns, Column::class, 'Table columns are an ordered list of column declarations.');
        $this->indexes = $indexes === null ? null : Check::listOf($indexes, IndexDeclaration::class, 'Native indexes are an ordered list of physical declarations.');
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

    /**
     * Finds physically named index fields using the native system case table.
     * @return list<Column>
     */
    public function matchingIndexColumns(string $name): array
    {
        $case = new NativeIdentifierCase();
        $key = $case->fold($name);
        $columns = [...$this->columns, ...array_map(static fn (ImplicitColumn $slot): Column => $slot->column, $this->implicit)];

        return array_values(array_filter($columns, static fn (Column $column): bool => $case->fold($column->name->value) === $key));
    }
}
