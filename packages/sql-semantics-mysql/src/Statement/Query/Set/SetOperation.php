<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Set;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\QueryTails;
use SqlSemantics\Platform\MySql\Rules\Query\SetFacts;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;

/**
 * A set operation: UNION, EXCEPT or INTERSECT of two queries.
 *
 * Operations of equal binding associate to the left, so a chain is a
 * left-deep tree; an operand that would need parentheses to keep its place
 * is refused: a set operation on the right that binds no more tightly, a
 * looser one on the left of INTERSECT, a query with a WITH clause or with
 * statement-level INTO or locking clauses, and on the right a query whose
 * ORDER BY, LIMIT or other trailing clauses would be read as applying to
 * the whole operation.
 *
 * Rule: MYSQL-SET-OPERATION-001. The output is derived by
 * MYSQL-SET-FACTS-001. Source: https://dev.mysql.com/doc/refman/8.4/en/set-operations.html,
 * https://dev.mysql.com/doc/refman/8.4/en/union.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the operands of a union
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t UNION SELECT b FROM u');
 *     [$query->query->left->from->name->name->value, $query->query->right->from->name->name->value] // => ['t', 'u']
 * @example Refusing a right operand that would associate differently without parentheses
 *     $one = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     $two = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2'))]);
 *     $three = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation($one, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation($two, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $three)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an ordered retained SELECT before UNION
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)], orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')))]);
 *     $right = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $right) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a retained final SELECT whose LIMIT would belong to UNION
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')));
 *     $left = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('3'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation($left, \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SetOperation implements Query
{
    use \SqlSemantics\Statement\Semantics\QuerySemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Query|LeadingUnion $left The left operand: a query, or the operands of a 5.x union before this UNION when the last of them keeps its own clauses
     * @param SetOperator $operator The operator
     * @param SetQuantifier|null $quantifier The written DISTINCT or ALL
     * @param Query $right The right operand
     */
    public function __construct(public readonly Query|LeadingUnion $left, public readonly SetOperator $operator, public readonly ?SetQuantifier $quantifier, public readonly Query $right, ?QueryFact $queryInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($queryInformation !== null) {
            $this->initializeQueryInformation($queryInformation);
        }
        Check::input(!$right instanceof self || $right->operator->tighter($operator), 'A set operation on the right of another is written in parentheses unless it binds more tightly.');
        Check::input(!$left instanceof self || !$operator->tighter($left->operator), 'A looser set operation on the left of INTERSECT is written in parentheses.');
        Check::input(!$left instanceof LeadingUnion || $operator === SetOperator::Union, 'A leading union continues with UNION.');
        Check::input(!$left instanceof QueryStatement && !$right instanceof QueryStatement, 'A query with INTO or locking clauses is written in parentheses as a set operand.');
        Check::input(!$left instanceof OrderedSetOperation && !$right instanceof OrderedSetOperation, 'A set operation ordered after its last SELECT ends its subquery.');
        Check::input(!$right instanceof QueryExpression && !($left instanceof QueryExpression && $left->with !== null), 'A query with a WITH clause or with ordering of its own is written in parentheses as a set operand.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SetOperands())->right($right);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\SetOperands())->left($left);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives both operands and the output columns.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new SetFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the operands around the operator.
     */
    public function render(Output $out): void
    {
        $out->node($this->left);
        (new QueryTails())->operation($this, $out);
    }
}
