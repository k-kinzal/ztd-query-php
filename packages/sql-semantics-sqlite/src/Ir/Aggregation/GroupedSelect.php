<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Aggregation;

use SqlSemantics\Platform\Sqlite\Ir\Arithmetic;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CastExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CollatedExpression;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Concatenation;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\UnaryExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\HexIntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\RealLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\BetweenTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Pattern\PatternTest;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\TruthTest;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite grouped count query with explicit text equivalence.
 *
 * @visibility public
 * @example Constructing the global count of the implicit row
 *     (new \SqlSemantics\Platform\Sqlite\Semantics())->analyze('SELECT count(*) AS n')->toString() // => 'SELECT count(*) AS "n"'
 */
final class GroupedSelect implements SemanticsIR
{
    use Snapshot;

    /**
     * @var list<GroupKey> The partition key tuple, with no keys for the global group
     */
    public readonly array $keys;

    /**
     * @var non-empty-list<GroupColumn> The ordered output fields
     */
    public readonly array $columns;

    /**
     * Establishes the grouping, output and single-occurrence constraints.
     *
     * @param list<GroupKey> $keys The input keys carrying their effective collations
     * @param non-empty-list<GroupColumn> $columns The named output fields
     */
    public function __construct(
        public readonly ?TableSource $source,
        array $keys,
        array $columns,
        public readonly Arithmetic|BetweenTest|BlobLiteral|CaseExpression|CastExpression|Coalesce|CollatedExpression|ColumnReference|Comparison|Concatenation|FoldedMembership|HexIntegerLiteral|IntegerLiteral|Logical|MembershipTest|NullLiteral|Parameter|PatternTest|RealLiteral|TextLiteral|TruthTest|UnaryExpression|null $where = null,
        public readonly ?CountComparison $having = null,
    ) {
        $grouping = Check::listOf($keys, GroupKey::class, 'A grouped query requires an owned key list.');
        $results = Check::listOf($columns, GroupColumn::class, 'A grouped query requires a nonempty result list.', 1);
        Check::input(count($grouping) <= 16 && count($results) <= 1024, 'The grouping key or result count exceeds the SQLite group profile.');
        $members = [];
        foreach ($grouping as $key) {
            Check::input(isset($source->columns[$key->column->slot]), 'Each grouping key must belong to its input.');
            $members[$key->column->slot] = true;
        }
        foreach ($results as $result) {
            $value = $result->value;
            if ($value instanceof ColumnReference) {
                Check::input($value->source === 0 && isset($members[$value->slot]), 'A plain grouped result must read a grouping key.');
            } else {
                Check::input($value->argument === null || isset($source->columns[$value->argument->slot]), 'A count slot must belong to the group input.');
            }
        }
        $predicates = [];
        $widths = $source === null ? [] : [count($source->columns)];
        if ($where !== null) {
            $predicates[] = [$where, 0, count($widths)];
        }
        if ($having !== null) {
            $argument = $having->count->argument;
            Check::input($argument === null || isset($source->columns[$argument->slot]), 'A HAVING count slot must belong to the group input.');
            $predicates[] = [$having->threshold, 0, count($widths)];
        }
        (new ExpressionScope())->check($widths, $predicates);
        $this->keys = $grouping;
        $this->columns = $results;
    }

    /**
     * Identifies the SQLite group semantics and builtin collation profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters/grouped-builtin';
    }

    /**
     * Emits the complete query from its grouping scope.
     */
    public function toString(): string
    {
        return (new GroupedSql())->render($this);
    }
}
