<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * WindowCall has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting WindowCall
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('SELECT f(ALL) OVER (base PARTITION BY g(ALL) OVER named ORDER BY h(ALL) FILTER (WHERE ?1) OVER another)', strict: false);
 *     $value = $statement->outputs[0]->expression;
 *     $value instanceof \SqlSemantics\Model\Scalar\Function\WindowCall // => true
 */
final class WindowCall extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly FunctionCall|AggregateCall|AllRowsAggregate|\SqlSemantics\Model\Scalar\Document\Construction\JsonObjectAggregate|\SqlSemantics\Model\Scalar\Document\Construction\JsonArrayAggregate $function,
        public readonly \SqlSemantics\Model\Window\Window $window,
    ) {
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
        return ExpressionKind::Window;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [$this->function, ...$this->window->expressions()];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return $this->function->spelling();
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->function, $this->window);
    }

}
