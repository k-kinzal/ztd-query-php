<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * A unary operation whose enum fixes its single operand and predicate behavior.
 * @example Inspecting a truth test
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT NULL IS NOT FALSE');
 *     $query->outputs[0]->expression->operator->value // => 'IS NOT FALSE'
 *     $query->outputs[0]->expression->nullability->value // => 'not-null'
 * @visibility public
 */
final class UnaryExpression extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly UnaryOperator $operator,
        public readonly Expression $operand,
    ) {
        if ($operator->postfix() && ($facts->nullability !== \SqlSemantics\Type\Nullability::NotNull || $facts->type->identity !== ($facts->type->dialect === \SqlSemantics\Dialect::PostgreSql ? \SqlSemantics\Type\Identity\BuiltinIdentity::Boolean : \SqlSemantics\Type\Identity\BuiltinIdentity::Integer))) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A truth or NULL test requires a non-NULL predicate result.');
        }
        if ($operator->truthTest() && $facts->type->dialect === \SqlSemantics\Dialect::Sqlite) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('This truth-test operation requires MySQL or PostgreSQL.');
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
        return [$this->operand];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return $this->operator->value;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->operator, $this->operand);
    }

}
