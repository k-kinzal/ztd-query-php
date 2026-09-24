<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * FunctionCall has explicit semantic operands and a fixed expression category.
 * @visibility public
 * @example Reading a call's name and arguments
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $call = (new \SqlSemantics\Binder($schema))->bind('SELECT custom_function(1, 2)')->outputs[0]->expression;
 *     $call->spelling() // => 'CUSTOM_FUNCTION'
 *     count($call->inputs()) // => 2
 */
final class FunctionCall extends Expression
{
    /**
     * @param list<Expression> $arguments
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly FunctionReference $function,
        public readonly array $arguments,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($arguments, Expression::class);
        Argument\ArgumentOrder::validate($arguments);
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
        return ExpressionKind::Function;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return $this->arguments;
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
        return new static($facts, $this->source, $this->function, $this->arguments);
    }

}
