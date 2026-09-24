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
 * A PostgreSQL VARIADIC argument: one array value passed as the whole variadic parameter, positionally or by name.
 *
 * @visibility public
 * @example Reading a variadic function argument
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT app.total(VARIADIC ARRAY[1, 2])');
 *     $statement->outputs[0]->expression->arguments[0]->kind->value // => 'variadic-argument'
 */
final class VariadicArgument extends Expression
{
    /**
     * @param Expression|NamedArgument $value The array value, or the named argument that carries it
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $value)
    {
        if ($facts->type->dialect !== \SqlSemantics\Dialect::PostgreSql || $value->type->dialect !== $facts->type->dialect) {
            throw new InvalidStructure('VARIADIC argument notation is a PostgreSQL form whose value uses the same dialect.');
        }
        if ($value instanceof self) {
            throw new InvalidStructure('VARIADIC marks one argument once.');
        }
        parent::__construct($facts, $source);
    }

    /**
     * Returns the fixed semantic category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::VariadicArgument;
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
     * Returns the notation keyword for diagnostics.
     */
    #[Override]
    public function spelling(): string
    {
        return 'VARIADIC';
    }

    /**
     * Replaces derived type facts while retaining the variadic value.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->value);
    }
}
