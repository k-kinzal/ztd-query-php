<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Creation;

use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;

/**
 * Distinguishes ordinary declarations from complete stored creation instructions.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CreationRoles
{
    /**
     * Identifies the absence of both declared columns and a query that supplies them.
     *
     * @param list<TableElement> $elements The ordered column and constraint definitions
     */
    public function columnless(array $elements, ?TableQuery $query): bool
    {
        return $query === null && array_filter($elements, static fn (TableElement $element): bool => $element instanceof ColumnDefinition) === [];
    }

    /**
     * Finds a fixed read-shape constraint retained by a creation instruction.
     */
    public function readProblem(?TableQuery $query): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        return $query === null ? null : (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($query->query);
    }

    /**
     * Identifies the complete definitions whose conditions belong to instruction use.
     *
     * @param list<TableElement> $elements The ordered column and constraint definitions
     * @param list<TableOption> $options The table options
     */
    public function deferred(array $elements, array $options, ?TableQuery $query, ?\SqlSemantics\Platform\MySql\Statement\Partition\Partitioning $partitioning = null, bool $temporary = false): bool
    {
        return $this->temporaryStorage($temporary, $options)
            || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Roles())->elements($elements)
            || (new PartitionKeyRole())->missing($elements, $partitioning, $query)
            || (new DeclarationConstraints())->problem($elements, $query) !== null
            || $this->readProblem($query) !== null
            || $this->columnless($elements, $query)
            || ($query !== null && array_filter($options, static fn (TableOption $option): bool => $option instanceof UnionOption) !== [])
            || (new LegacySubqueries())->columns($elements);
    }
    /**
     * Identifies a temporary definition requesting a positive file extension size.
     * The last option wins, and zero disables the request. The operand conflict
     * is intrinsic; schema and query dependencies still precede its SQL condition.
     * Observed through SQL on MySQL 8.4.7.
     *
     * @param list<TableOption> $options The ordered table options
     */
    public function temporaryStorage(bool $temporary, array $options): bool
    {
        $positive = false;
        foreach ($options as $option) {
            if ($option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption) {
                $digits = $option->size->number->text ?? rtrim($option->size->word->value ?? '', 'KMGkmg');
                $positive = trim($digits, '0') !== '';
            }
        }

        return $temporary && $positive;
    }

}
