<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\ExpressionFacts;
use SqlSemantics\Platform\MySql\Rules\Query\SetFacts;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * A set operation of a 5.6 subquery whose ORDER BY or LIMIT is written after the clauses of its last SELECT.
 *
 * In a 5.6 subquery or derived table the ORDER BY and LIMIT written inside
 * the last unparenthesized SELECT of a union apply to the union, unless an
 * ORDER BY or LIMIT follows that SELECT (`opt_union_order_or_limit`): then
 * the server makes its fake query block the global parameters of the union,
 * the last SELECT keeps its own ORDER BY, LIMIT and locking clauses, and the
 * clauses written after it order and limit the result of the union. This
 * node holds that form: the operation, its last SELECT with its own
 * clauses, and the ORDER BY and LIMIT of the result. Nothing follows it in
 * the 5.6 grammar, so it is never an operand or a body itself.
 *
 * Rule: MYSQL-ORDERED-SET-OPERATION-001. Both operands are derived by
 * MYSQL-SET-FACTS-001; the ORDER BY and LIMIT see the output columns of the
 * operation as MYSQL-QUERY-EXPRESSION-FACTS-001 does. Source:
 * https://dev.mysql.com/doc/refman/5.6/en/union.html,
 * https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_yacc.yy (`union_order_or_limit`).
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the ordering of a 5.6 union written after the locking clause of its last SELECT
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.6.51'))->analyze('SELECT (SELECT 1 UNION SELECT a FROM t FOR UPDATE ORDER BY 1)');
 *     $union = $query->statement->items[0]->expression->query;
 *     [count($union->right->locking), count($union->orderBy)] // => [1, 1]
 * @example Refusing a last SELECT without clauses of its own
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     $two = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation($one, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $two, [], new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class OrderedSetOperation implements Query
{
    use Snapshot;

    /**
     * @var list<OrderItem> The ORDER BY items of the result in written order
     */
    public readonly array $orderBy;

    /**
     * @param Query|LeadingUnion $left The operands before the last SELECT
     * @param SetOperator $operator The operator before the last SELECT
     * @param SetQuantifier|null $quantifier The written DISTINCT or ALL
     * @param Select $right The last SELECT with its own ORDER BY, LIMIT or locking clauses
     * @param list<OrderItem> $orderBy The ORDER BY items of the result
     * @param Limit|null $limit The LIMIT of the result
     */
    public function __construct(
        public readonly Query|LeadingUnion $left,
        public readonly SetOperator $operator,
        public readonly ?SetQuantifier $quantifier,
        public readonly Select $right,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
    ) {
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
        (new SortScopes())->check($this->orderBy);
        Check::input($this->orderBy !== [] || $limit !== null, 'An ordered set operation holds an ORDER BY or a LIMIT of its result.');
        Check::input($right->trailed() && $right->late === null && $right->into === null && $right->procedure === null, 'The last SELECT of an ordered set operation writes an ORDER BY, LIMIT or locking clause of its own and nothing after them.');
        Check::input(!$left instanceof SetOperation || !$operator->tighter($left->operator), 'A looser set operation on the left of INTERSECT is written in parentheses.');
        Check::input(!$left instanceof LeadingUnion || $operator === SetOperator::Union, 'A leading union continues with UNION.');
        Check::input(!$left instanceof self && !$left instanceof QueryStatement && !($left instanceof QueryExpression && $left->with !== null), 'A query with a WITH clause, INTO, locking clauses or an ordering of its result is written in parentheses as a set operand.');
    }

    /**
     * Derives both operands, the output columns, the ordering and the limit.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        Check::input($derivation->context->profile->grammar === GrammarRelease::MySql5651, 'A set operation ordered after the clauses of its last SELECT needs MySQL 5.6.');
        $fact = (new SetFacts())->operands($this->left, $this->operator, $this->right, $derivation, $outer);
        (new ExpressionFacts())->ordering($fact, $this->orderBy, $this->limit, $derivation, $outer);

        return $fact;
    }

    /**
     * Writes the operands around the operator, then the ORDER BY and LIMIT of the result.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword($this->operator->value);
        if ($this->quantifier !== null) {
            $out->keyword($this->quantifier->value);
        }
        $out->node($this->right);
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
