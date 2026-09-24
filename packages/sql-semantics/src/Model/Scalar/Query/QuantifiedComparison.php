<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * QuantifiedComparison has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting QuantifiedComparison
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('SELECT 1 = ANY (SELECT 1)');
 *     $query = $binder->bind('SELECT 1, 2');
 *     $expression = $statement->outputs[0]->expression;
 *     $expression instanceof \SqlSemantics\Model\Scalar\Query\QuantifiedComparison // => true
 * @example Comparing through a pattern operator or an explicitly named operator
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind("SELECT 'a' NOT LIKE ALL (SELECT 'b'), 1 OPERATOR(geo.<->) ANY (SELECT 2)");
 *     [$statement->outputs[0]->expression->spelling(), $statement->outputs[1]->expression->spelling()] // => ['NOT LIKE ALL', 'OPERATOR(geo.<->) ANY']
 */
final class QuantifiedComparison extends Expression
{
    /**
     * A pattern or named operator is PostgreSQL-only; only LIKE and ILIKE may be negated.
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly Expression $value,
        public readonly ComparisonOperator|\SqlSemantics\Model\Scalar\Conditional\PatternOperator|\SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator $operator,
        public readonly Quantifier $quantifier,
        public readonly \SqlSemantics\Model\BoundQuery $query,
        public readonly bool $negated = false,
    ) {
        $pattern = $operator instanceof \SqlSemantics\Model\Scalar\Conditional\PatternOperator;
        if (!$operator instanceof ComparisonOperator && $facts->type->dialect !== \SqlSemantics\Dialect::PostgreSql || $pattern && !in_array($operator, [\SqlSemantics\Model\Scalar\Conditional\PatternOperator::Like, \SqlSemantics\Model\Scalar\Conditional\PatternOperator::ILike], true) || $negated && !$pattern) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A quantified comparison uses a comparison operator, or in PostgreSQL a possibly negated LIKE or ILIKE or a named operator.');
        }
        if ($query->origin->dialect !== $facts->type->dialect || !\SqlSemantics\Model\Validation\QueryComparison::compatible($value, $query)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A subquery comparison requires compatible dialects and row widths.');
        }
        parent::__construct($facts, $source);
        foreach ($this->inputs() as $input) {
            if ($input->type->dialect !== $facts->type->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Expression operands cannot mix SQL dialects.');
            }
        }
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Subquery;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, ...array_map(static fn ($output): Expression => $output->expression, $this->query->resultColumns())];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return ($this->negated ? 'NOT ' : '') . ($this->operator instanceof \SqlSemantics\Model\Scalar\Operator\Qualified\QualifiedOperator ? $this->operator->spelling() : $this->operator->value) . ' ' . $this->quantifier->value;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->operator, $this->quantifier, $this->query, $this->negated);
    }

    /**
     * Returns the required nested query that supplies this expression.
     */
    #[Override]
    public function subquery(): \SqlSemantics\Model\BoundQuery
    {
        return $this->query;
    }
}
