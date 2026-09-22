<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * UnresolvedVariableReference has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting UnresolvedVariableReference
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build());
 *     $statement = $binder->bind('PREPARE s FROM @sql', strict: false);
 *     $statement->sql instanceof \SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference // => true
 */
final class UnresolvedVariableReference extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly string $name,
        public readonly \SqlSemantics\Schema\VariableScope $scope,
    ) {
        if ($name === '' || $facts->type->name !== 'unknown') {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An unresolved variable requires its name and an unknown type.');
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
        return ExpressionKind::UnresolvedVariable;
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
        return $this->name;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->name, $this->scope);
    }

}
