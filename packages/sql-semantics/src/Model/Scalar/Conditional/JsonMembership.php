<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\StatementOperands;

/**
 * Tests a value for membership in a JSON array without evaluating either operand.
 * @visibility public
 * @example Reading the JSON membership operands
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("SELECT 1 MEMBER OF ('[1,2]')");
 *     $statement->outputs[0]->expression->value->spelling() // => '1'
 */
final class JsonMembership extends Expression
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $value, public readonly Expression $array)
    {
        if ($facts->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MEMBER OF requires MySQL.');
        }
        StatementOperands::expressions([$value, $array], $facts->type->dialect);
        parent::__construct($facts, $source);
    }

    /**
     * Returns the operator category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Operator;
    }

    /**
     * @return list<Expression> The searched value followed by the JSON array input
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, $this->array];
    }

    /**
     * Returns the membership operator name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'MEMBER OF';
    }

    /**
     * Replaces inferred facts while preserving both operands.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value, $this->array);
    }
}
