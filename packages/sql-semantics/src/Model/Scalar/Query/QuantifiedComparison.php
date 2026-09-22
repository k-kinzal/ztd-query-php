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
 */
final class QuantifiedComparison extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly Expression $value,
        public readonly ComparisonOperator $operator,
        public readonly Quantifier $quantifier,
        public readonly \SqlSemantics\Model\BoundQuery $query,
    ) {
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
        return $this->operator->value . ' ' . $this->quantifier->value;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->operator, $this->quantifier, $this->query);
    }

    #[Override]
    public function subquery(): \SqlSemantics\Model\BoundQuery
    {
        return $this->query;
    }
}
