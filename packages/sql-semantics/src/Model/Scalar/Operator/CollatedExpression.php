<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * Applies a named collation to an operand without evaluating or changing its value.
 *
 * @visibility public
 */
final class CollatedExpression extends Expression
{
    /**
     * Records the operand and the collation lookup identity.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $operand, public readonly QualifiedName $collation)
    {
        parent::__construct($facts, $source);
        if ($facts->type->dialect !== $operand->type->dialect) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A collated operand must use the same dialect as its result.');
        }
    }

    /**
     * Returns the fixed semantic category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Collation;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand];
    }

    /**
     * Returns the operator spelling for diagnostics.
     */
    #[Override]
    public function spelling(): string
    {
        return 'COLLATE';
    }

    /**
     * Replaces derived type facts while retaining the collation operation.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->operand, $this->collation);
    }
}
