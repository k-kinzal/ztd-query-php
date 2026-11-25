<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\ColumnKind;
use SqlSemantics\Platform\MySql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\MySql\Ir\TableSource;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete grouped projection over one absolute ordinary table.
 *
 * @visibility public
 * @example Inspecting the result of an aggregate query
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $table = $semantics->context(['CREATE TABLE public.group_source (id INT)']);
 *     $semantics->analyze('SELECT COUNT(*) AS n FROM public.group_source', $table) instanceof \SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedSelect // => true
 */
final class GroupedSelect implements SemanticsIR
{
    use Snapshot;

    /**
     * @var list<ColumnReference> The ordered partitioning keys. Empty for the global group.
     */
    public readonly array $keys;

    /**
     * @var non-empty-list<GroupColumn> The result fields in projection order.
     */
    public readonly array $columns;

    /**
     * Checks the projected and grouped fields under the same absolute input layout.
     *
     * @param list<ColumnReference> $keys The ordered key tuple
     * @param non-empty-list<GroupColumn> $columns The named output fields
     */
    public function __construct(
        public readonly TableSource $source,
        array $keys,
        array $columns,
        public readonly GrammarRelease $release = GrammarRelease::MySql910,
        public readonly BooleanLiteral|IntegerComparison|IntegerMembership|IntegerRange|TextComparison|LogicalPredicate|NullLiteral|NullTest|null $where = null,
        public readonly GroupedComparison|BooleanLiteral|NullLiteral|null $having = null,
    ) {
        $grouping = Check::listOf($keys, ColumnReference::class, 'A grouped SELECT needs an owned list of input keys.');
        $results = Check::listOf($columns, GroupColumn::class, 'A grouped SELECT needs a nonempty list of results.', 1);
        Check::input(count($grouping) <= 16 && count($results) <= 1024, 'The grouping key or result count exceeds the grouped profile.');
        $members = [];
        foreach ($grouping as $key) {
            Check::input($key->occurrence === 0 && ($source->columnTypes[$key->slot] ?? null) instanceof ColumnKind, 'Each grouping key must belong to its input.');
            $members[$key->slot] = true;
        }
        $operands = [];
        foreach ($results as $result) {
            $operands[] = $result->value;
        }
        if ($having instanceof GroupedComparison) {
            array_push($operands, $having->left, $having->right);
        }
        $scope = new OccurrenceScope();
        foreach ($operands as $value) {
            if ($value instanceof ColumnReference) {
                Check::input($value->occurrence === 0 && isset($members[$value->slot]), 'A plain grouped result must read a grouping key.');
            } elseif ($value instanceof Aggregate && $value->argument instanceof TextColumnReference) {
                $scope->textColumn($value->argument, [$source]);
            } elseif ($value instanceof Aggregate) {
                Check::input($value->argument === null || ($source->columnTypes[$value->argument->slot] ?? null) instanceof ColumnKind, 'The aggregate slot must belong to the group input.');
            }
        }
        foreach ($results as $result) {
            $value = $result->value;
            $name = $result->name->value;
            Check::input(
                !$value instanceof ColumnReference || $name === ltrim($name, " \t\r\n\v\f") || $source->columns[$value->slot]->value === $name,
                'An intrinsic MySQL grouped name must equal its physical input field.'
            );
        }
        $scope->predicate($where, [$source]);
        $this->keys = $grouping;
        $this->columns = $results;
    }

    /**
     * Identifies the anchored database profile for grouping and native aggregates.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/grouped';
    }

    /**
     * Emits the checked query clauses in execution order.
     */
    public function toString(): string
    {
        return (new GroupedSql())->render($this);
    }
}
