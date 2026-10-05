<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A grouping set of GROUP BY: the empty set `()`, ROLLUP, CUBE or GROUPING SETS.
 *
 * Mirrors PostgreSQL's `GroupingSet`. ROLLUP and CUBE hold expressions and
 * grouping rows (a row written without ROW groups its fields together);
 * GROUPING SETS holds expressions, grouping rows and nested grouping sets. The selection that holds the
 * set derives its expressions with the rules of GROUP BY.
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-GROUPING-SETS.
 *
 * @visibility public
 * @example Reading a ROLLUP
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 GROUP BY ROLLUP (1), ()');
 *     [$query->statement->groupBy[0]->kind->value, count($query->statement->groupBy[1]->members)] // => ['ROLLUP', 0]
 * @example Refusing an empty ROLLUP
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSet(\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSetKind::Rollup) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class GroupingSet implements Node
{
    use Snapshot;

    /**
     * @var list<Scalar|GroupingRow|GroupingSet> The members in written order
     */
    public readonly array $members;

    /**
     * @param GroupingSetKind $kind The kind of set
     * @param list<Scalar|GroupingRow|GroupingSet> $members The members in written order: none for the empty set, expressions and rows for ROLLUP and CUBE, expressions, rows and sets for GROUPING SETS
     */
    public function __construct(public readonly GroupingSetKind $kind, array $members = [])
    {
        $message = 'ROLLUP and CUBE hold expressions and grouping rows; GROUPING SETS also holds grouping sets.';
        $this->members = (new ClosedList())->of($members, $kind === GroupingSetKind::Sets ? [Scalar::class, GroupingRow::class, self::class] : [Scalar::class, GroupingRow::class], $message);
        foreach ($this->members as $member) {
            Check::input(!$member instanceof Scalar || (new Positions())->value($member) === null, 'An integer constant in GROUP BY is an output position.');
        }
        Check::input(($kind === GroupingSetKind::Empty) === ($this->members === []), 'Only the empty grouping set has no member.');
    }

    /**
     * Writes the set.
     */
    public function render(Output $out): void
    {
        if ($this->kind !== GroupingSetKind::Empty) {
            $out->keyword(...explode(' ', $this->kind->value));
        }
        $out->symbol('(')->list($this->members)->symbol(')');
    }
}
