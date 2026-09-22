<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * Membership against ordered value expressions with the same known row width.
 * @visibility public
 * @example Reading the candidate values
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT 1 IN (2, 3)');
 *     count($statement->outputs[0]->expression->choices) // => 2
 */
final class InList extends Expression
{
    /**
     * @param list<Expression> $choices
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly Expression $value,
        public readonly array $choices,
        public readonly bool $negated,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($choices, Expression::class);
        if ($choices === [] && $facts->type->dialect !== \SqlSemantics\Dialect::Sqlite) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An IN list requires a candidate outside SQLite.');
        }
        foreach ($choices as $choice) {
            if (!\SqlSemantics\Model\Validation\QueryComparison::operands($value, $choice)) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Each IN candidate must have the compared value\'s row width.');
            }
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
        return ExpressionKind::Operator;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, ...$this->choices];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return $this->negated ? 'NOT IN' : 'IN';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->choices, $this->negated);
    }

}
