<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * OrdinalityColumn has explicit semantic operands and a fixed expression category.
 * @visibility public
 * @example Numbering the rows of a table function call
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $call = (new \SqlSemantics\Binder($schema))->bind('SELECT custom_function(1, 2)')->outputs[0]->expression;
 *     $column = new \SqlSemantics\Model\Scalar\Function\OrdinalityColumn($call->facts, $call->source, $call);
 *     $column->spelling() // => 'ORDINALITY'
 *     $column->inputs()[0] === $call // => true
 */
final class OrdinalityColumn extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly Expression $call,
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
        return ExpressionKind::Function;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [$this->call];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return 'ORDINALITY';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->call);
    }

}
