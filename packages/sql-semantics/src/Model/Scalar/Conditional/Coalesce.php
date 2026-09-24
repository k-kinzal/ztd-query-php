<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * Coalesce has explicit semantic operands and a fixed expression category.
 * @visibility public
 * @example Counting the alternatives
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT COALESCE(NULL, 1)');
 *     count($statement->outputs[0]->expression->arguments) // => 2
 */
final class Coalesce extends Expression
{
    /**
     * @param list<Expression> $arguments
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly array $arguments,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($arguments, Expression::class);
        if ($arguments === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('COALESCE requires arguments.');
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
        return ExpressionKind::Coalesce;
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
        return 'COALESCE';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->arguments);
    }

}
