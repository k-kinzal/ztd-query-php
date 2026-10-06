<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\SetOperationFacts;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * UNION, INTERSECT or EXCEPT of two queries.
 *
 * Mirrors a `SelectStmt` with `op` and `all`. An operand is a selection, a
 * VALUES list, a TABLE query, another set operation or a parenthesized
 * query; a selection or TABLE query with ORDER BY, LIMIT or locking clauses
 * must be parenthesized. Because INTERSECT binds more tightly and operators
 * associate from the left, a set operation that would need parentheses to
 * keep its place is refused as an operand. The facts follow PG-SET-OPERATION-001.
 * Source: https://www.postgresql.org/docs/17/queries-union.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a set operation
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a UNION ALL SELECT 2');
 *     [$query->statement->operator->value, $query->statement->quantifier->value, $query->field(0)->name->value] // => ['UNION', 'ALL', 'a']
 * @example Refusing a right operand that would re-associate
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()])]);
 *     $two = new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()])]);
 *     $three = new \SqlSemantics\Platform\PostgreSql\Statement\Query\ValuesList([new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\ValuesRow([new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()])]);
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation($one, \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Union, new \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation($two, \SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperator::Except, $three)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SetOperation implements Statement, Query, OutputNaming
{
    use Snapshot;

    /**
     * @param Query $left The first operand
     * @param SetOperator $operator The operator
     * @param Query $right The second operand
     * @param SetQuantifier|null $quantifier The ALL or DISTINCT written after the operator
     */
    public function __construct(public readonly Query $left, public readonly SetOperator $operator, public readonly Query $right, public readonly ?SetQuantifier $quantifier = null)
    {
        foreach ([$left, $right] as $operand) {
            Check::input(
                $operand instanceof ParenthesizedQuery || $operand instanceof ValuesList || $operand instanceof self
                || ($operand instanceof Select && $operand->options === null) || ($operand instanceof TableQuery && $operand->options === null),
                'A set operand is a selection, VALUES, TABLE, a set operation or a parenthesized query.',
            );
        }
        Check::input(!$left instanceof self || $left->operator->level() >= $operator->level(), 'A weaker set operation on the left needs parentheses.');
        Check::input(!$right instanceof self || $right->operator->level() > $operator->level(), 'A set operation on the right that does not bind more tightly needs parentheses.');
    }

    /**
     * Answers the name of the first output column, which the first operand gives.
     */
    public function outputName(): ?Name
    {
        return $this->left instanceof OutputNaming ? $this->left->outputName() : null;
    }

    /**
     * Derives the set operation as a statement root.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new QueryRoots())->derive($this, $derivation);
    }

    /**
     * Derives both operands and the combined output.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        return (new SetOperationFacts())->derive($this, $derivation, $outer);
    }

    /**
     * Writes the operands around the operator and its quantifier.
     */
    public function render(Output $out): void
    {
        $out->node($this->left)->keyword($this->operator->value);
        if ($this->quantifier !== null) {
            $out->keyword($this->quantifier->value);
        }
        $out->node($this->right);
    }
}
