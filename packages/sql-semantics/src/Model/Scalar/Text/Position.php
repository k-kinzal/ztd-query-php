<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Text;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Searches for a needle within a string or binary input, without executing the search.
 * @visibility public
 * @example Inspecting the search operands
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT POSITION('a' IN 'cat')");
 *     $query->outputs[0]->expression->needle->spelling() // => "'a'"
 */
final class Position extends Expression
{
    /**
     * Derives integer result facts from the two required search operands.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $needle, public readonly Expression $haystack)
    {
        $dialect = $needle->type->dialect;
        if ($dialect === Dialect::Sqlite || $haystack->type->dialect !== $dialect) {
            throw new InvalidStructure('POSITION requires two operands in the same MySQL or PostgreSQL dialect.');
        }
        $nullable = match (true) {
            $needle->nullability === Nullability::AlwaysNull || $haystack->nullability === Nullability::AlwaysNull => Nullability::AlwaysNull,
            $needle->nullability === Nullability::Unknown || $haystack->nullability === Nullability::Unknown => Nullability::Unknown,
            $needle->nullability === Nullability::MaybeNull || $haystack->nullability === Nullability::MaybeNull => Nullability::MaybeNull,
            default => Nullability::NotNull,
        };
        $extensions = $nullable === Nullability::NotNull ? [] : array_values(array_unique([...$needle->nullExtendedBy, ...$haystack->nullExtendedBy]));
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin($dialect, $dialect === Dialect::MySql ? 'bigint' : 'integer'), $nullable, $extensions), $source);
    }

    /**
     * Identifies a substring search.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Position;
    }

    /**
     * @return list<Expression> The needle followed by the searched input
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->needle, $this->haystack];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'POSITION';
    }

    /**
     * Preserves the facts derived from the search operands.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Position facts are derived from its search operands.');
        }
        return new static($this->source, $this->needle, $this->haystack);
    }
}
