<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * AggregateCall has explicit semantic operands and a fixed expression category.
 * @visibility public
 */
final class AggregateCall extends Expression
{
    /**
     * @param list<Expression> $arguments
     * @param list<\SqlSemantics\Model\Ordering> $orderBy
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly FunctionReference $function,
        public readonly array $arguments,
        public readonly ArgumentMode $mode,
        public readonly array $orderBy,
        public readonly ?Expression $filter,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($arguments, Expression::class);
        \SqlSemantics\Model\Validation\Collections::objects($orderBy, \SqlSemantics\Model\Ordering::class);
        foreach ($orderBy as $order) {
            if (!$order->key instanceof Expression) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('This ordering requires an input expression, not a result alias or position.');
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
        return ExpressionKind::Aggregate;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [...$this->arguments, ...array_map(static fn (\SqlSemantics\Model\Ordering $order): Expression => $order->key instanceof Expression ? $order->key : throw new \SqlSemantics\Model\Validation\InvalidStructure('This ordering is evaluated before projection and requires an expression.'), $this->orderBy), ...($this->filter === null ? [] : [$this->filter])];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return strtoupper(implode('.', $this->function->name()->parts));
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->function, $this->arguments, $this->mode, $this->orderBy, $this->filter);
    }

}
