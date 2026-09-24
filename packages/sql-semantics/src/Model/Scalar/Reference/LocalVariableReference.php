<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Reference;

use Override;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalVariable;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * A reference to a stored program parameter or local variable, distinct from user variables and columns.
 * @visibility public
 * @example Reading a local variable reference
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CREATE FUNCTION f(a INT) RETURNS INT RETURN a');
 *     $statement->body->value instanceof \SqlSemantics\Model\Scalar\Reference\LocalVariableReference // => true
 *     $statement->body->value->type->name // => 'integer'
 */
final class LocalVariableReference extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source, public readonly LocalVariable $variable)
    {
        if (serialize($facts->type) !== serialize($variable->domain->type)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A local variable reference retains its declared type.');
        }
        parent::__construct($facts, $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::LocalVariable;
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
        return $this->variable->name;
    }

    /**
     * @visibility SqlSemantics
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->variable);
    }
}
