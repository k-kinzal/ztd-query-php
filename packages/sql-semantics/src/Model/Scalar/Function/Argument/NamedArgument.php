<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function\Argument;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL function argument in named notation: the parameter it names and the value passed to it.
 * The `=>` and `:=` spellings name the parameter in the same way.
 *
 * @visibility public
 * @example Reading a named function argument
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT app.shift(1, days := 2)');
 *     $argument = $statement->outputs[0]->expression->arguments[1];
 *     [$argument->name, $argument->value->spelling()] // => ['days', '2']
 */
final class NamedArgument extends Expression
{
    /**
     * @param string $name Parameter name, unquoted
     * @param Expression $value Argument value, not evaluated by binding
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly string $name, public readonly Expression $value)
    {
        if ($facts->type->dialect !== \SqlSemantics\Dialect::PostgreSql || $value->type->dialect !== $facts->type->dialect) {
            throw new InvalidStructure('Named argument notation is a PostgreSQL form whose value uses the same dialect.');
        }
        if ($name === '') {
            throw new InvalidStructure('A named argument requires a nonempty parameter name.');
        }
        if ($value instanceof self || $value instanceof VariadicArgument) {
            throw new InvalidStructure('A named argument takes a value expression, not another argument notation.');
        }
        parent::__construct($facts, $source);
    }

    /**
     * Returns the fixed semantic category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::NamedArgument;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value];
    }

    /**
     * Returns the named parameter for diagnostics.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->name;
    }

    /**
     * Replaces derived type facts while retaining the parameter name.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->name, $this->value);
    }
}
