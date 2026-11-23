<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Aggregation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerDistinctness;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerRange;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\NullTest;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\Text\TextComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete grouped projection over one absolute ordinary table.
 *
 * @visibility public
 * @example Inspecting the result of an aggregate query
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $table = $semantics->context(['CREATE TABLE public.group_source (id INT)']);
 *     $semantics->analyze('SELECT COUNT(*) AS n FROM public.group_source', $table) instanceof \SqlSemantics\Platform\PostgreSql\Ir\Aggregation\GroupedSelect // => true
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
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly BooleanLiteral|IntegerComparison|IntegerDistinctness|IntegerRange|LogicalPredicate|NullLiteral|NullTest|TextComparison|TruthTest|null $where = null,
    ) {
        $grouping = Check::listOf($keys, ColumnReference::class, 'A grouped SELECT needs an owned list of input keys.');
        $results = Check::listOf($columns, GroupColumn::class, 'A grouped SELECT needs a nonempty list of results.', 1);
        Check::input(count($grouping) <= 16 && count($results) <= 1024, 'The grouping key or result count exceeds the grouped profile.');
        $members = [];
        foreach ($grouping as $key) {
            Check::input($key->occurrence === 0 && $source->columnKind($key->slot) === ColumnKind::Int4, 'Each grouping key must belong to its input.');
            $members[$key->slot] = true;
        }
        foreach ($results as $result) {
            $value = $result->value;
            if ($value instanceof ColumnReference) {
                Check::input($value->occurrence === 0 && isset($members[$value->slot]), 'A plain grouped result must read a grouping key.');
            } else {
                Check::input($value->argument === null || $source->columnKind($value->argument->slot) === ColumnKind::Int4, 'The aggregate slot must belong to the group input.');
            }
        }
        (new OccurrenceScope())->predicate($where, [$source]);
        $this->keys = $grouping;
        $this->columns = $results;
    }

    /**
     * Identifies the anchored database profile for grouping and native aggregates.
     */
    public function profileId(): string
    {
        return $this->release->value . '/UTF8/standard-conforming-strings=on/native/grouped';
    }

    /**
     * Emits the checked query clauses in execution order.
     */
    public function toString(): string
    {
        return (new GroupedSql())->render($this);
    }
}
