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
 * Removes characters from one or both ends of a string, without evaluating the removal.
 * Without a removed-characters operand the database removes spaces (MySQL) or whitespace (PostgreSQL).
 * @visibility public
 * @example Inspecting the side, removed characters and source string of a trim
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT TRIM(LEADING 'x' FROM 'xxa')");
 *     $query->outputs[0]->expression->side->value // => 'LEADING'
 *     $query->outputs[0]->expression->characters?->spelling() // => "'x'"
 *     $query->outputs[0]->expression->string->spelling() // => "'xxa'"
 */
final class Trim extends Expression
{
    /**
     * Derives text result facts from the source string and the removed characters.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly TrimSide $side, public readonly ?Expression $characters, public readonly Expression $string)
    {
        $dialect = $string->type->dialect;
        if ($dialect === Dialect::Sqlite || ($characters !== null && $characters->type->dialect !== $dialect)) {
            throw new InvalidStructure('TRIM requires operands in the same MySQL or PostgreSQL dialect.');
        }
        $operands = $characters === null ? [$string] : [$characters, $string];
        $nullabilities = array_map(static fn (Expression $operand): Nullability => $operand->nullability, $operands);
        $nullable = match (true) {
            in_array(Nullability::AlwaysNull, $nullabilities, true) => Nullability::AlwaysNull,
            in_array(Nullability::Unknown, $nullabilities, true) => Nullability::Unknown,
            in_array(Nullability::MaybeNull, $nullabilities, true) => Nullability::MaybeNull,
            default => Nullability::NotNull,
        };
        $extensions = $nullable === Nullability::NotNull ? [] : array_values(array_unique(array_merge(...array_map(static fn (Expression $operand): array => $operand->nullExtendedBy, $operands))));
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin($dialect, 'text'), $nullable, $extensions), $source);
    }

    /**
     * Identifies a trim operation.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Trim;
    }

    /**
     * @return list<Expression> The removed characters, when written, followed by the source string
     */
    #[Override]
    public function inputs(): array
    {
        return $this->characters === null ? [$this->string] : [$this->characters, $this->string];
    }

    /**
     * Returns the fixed operation name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'TRIM';
    }

    /**
     * Preserves the facts derived from the trim operands.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Trim facts are derived from its operands.');
        }
        return new static($this->source, $this->side, $this->characters, $this->string);
    }
}
