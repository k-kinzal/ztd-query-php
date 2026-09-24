<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * Wildcard has explicit semantic operands and a fixed expression category.
 * @visibility public
 * @example Reading the qualifier of an unexpanded wildcard
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build()))->bind('SELECT m.* FROM missing m', strict: false);
 *     $statement->outputs[0]->expression->qualifier[0] // => 'm'
 */
final class Wildcard extends Expression
{
    /**
     * @param list<string> $qualifier
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly array $qualifier,
    ) {
        \SqlSemantics\Model\Validation\Collections::strings($qualifier);
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
        return ExpressionKind::Wildcard;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return '*';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->qualifier);
    }

    /**
     * Returns unquoted identifier parts that identify this reference.
     */
    #[Override]
    public function referenceParts(): array
    {
        return $this->qualifier;
    }
}
