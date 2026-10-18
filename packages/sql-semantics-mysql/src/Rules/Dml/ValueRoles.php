<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall;
use SqlSemantics\Statement\Node;

/**
 * Constrains dormant operands to the clauses a VALUES write never contextualizes.
 *
 * The check is structural and independent of catalogs, sessions and execution.
 * Statement and scalar constructors check this through the shared graph invariants.
 *
 * @visibility SqlSemantics\Platform\MySql
 * @example Refusing a dormant call even in an unused ordinary CTE
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall(new \SqlSemantics\Statement\Identifier\Name('ABS'));
 *     $body = new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody(new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($call)]));
 *     $with = new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, [new \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('x'), [], $body)]);
 *     $select = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression($with, $select)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a dormant call in a stored instruction
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall(new \SqlSemantics\Statement\Identifier\Name('ABS'));
 *     $body = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($call)]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('p')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), $body) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a VALUES-write wrapper inside an ordinary scalar predicate
 *     $rows = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses(null, $rows, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row)]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a VALUES-write wrapper inside a quantified scalar comparison
 *     $rows = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\ValuesClauses(null, $rows, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem($row)]);
 *     new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal, \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier::Any, $query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class ValueRoles
{
    /**
     * Refuses an uncontextualized-only value outside an unused VALUES-source clause.
     */
    public function check(Node $root): void
    {
        /** @var list<array{Node, bool, array<int, true>}> $pending */
        $pending = [[$root, false, []]];
        while ($pending !== []) {
            [$node, $inactive, $wrappers] = array_pop($pending);
            if ($node instanceof InsertQuery) {
                $wrappers = array_fill_keys(array_map(spl_object_id(...), $node->wrappers()), true);
            }
            Check::input(!$node instanceof DormantCall || $inactive, 'A dormant native signature belongs only to an unused VALUES-write clause.');
            Check::input(!$node instanceof ValuesClauses || isset($wrappers[spl_object_id($node)]) || $inactive, 'Retained VALUES ordering belongs to a VALUES write.');
            foreach (get_object_vars($node) as $property => $value) {
                $body = $node instanceof QueryExpression || $node instanceof ValuesClauses ? 'body' : 'query';
                $unused = $inactive || (isset($wrappers[spl_object_id($node)]) && $property !== $body);
                foreach (is_array($value) ? $value : [$value] as $child) {
                    if ($child instanceof Node) {
                        $pending[] = [$child, $unused, $wrappers];
                    }
                }
            }
        }
    }
}
