<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * VariableReference has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting VariableReference
 *     $type = new \SqlSemantics\Type\TypeDescriptor(\SqlSemantics\Dialect::MySql, \SqlSemantics\Type\Identity\BuiltinIdentity::Text);
 *     $variable = new \SqlSemantics\Schema\VariableDefinition('sql', \SqlSemantics\Schema\VariableScope::User, $type);
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()->withVariables($variable);
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('PREPARE s FROM @sql');
 *     $statement->sql instanceof \SqlSemantics\Model\Scalar\Reference\VariableReference // => true
 */
final class VariableReference extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly \SqlSemantics\Schema\VariableDefinition $definition,
    ) {
        if (serialize($facts->type) !== serialize($definition->type)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A bound reference must retain its declaration type.');
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
        return ExpressionKind::Variable;
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
        return $this->definition->name;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->definition);
    }

}
