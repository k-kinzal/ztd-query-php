<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Value;

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
 * A MySQL `DATE 'text'`, `TIME 'text'` or `TIMESTAMP 'text'` constant, including its ODBC `{d 'text'}` spelling.
 * @visibility public
 * @example Reading the temporal category and its string
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT {ts '2024-01-02 03:04:05'}");
 *     $literal = $query->outputs[0]->expression;
 *     $literal->category // => \SqlSemantics\Model\Scalar\Value\LiteralKind::Timestamp
 *     $literal->type->name // => 'datetime'
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => "SELECT TIMESTAMP '2024-01-02 03:04:05'"
 */
final class TemporalLiteral extends Expression
{
    /**
     * Derives a non-NULL DATE, TIME or DATETIME constant from its string.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly LiteralKind $category, public readonly Literal $text)
    {
        if ($text->type->dialect !== Dialect::MySql || $text->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A MySQL temporal literal is written with a string literal.');
        }
        $type = [LiteralKind::Date->value => 'date', LiteralKind::Time->value => 'time', LiteralKind::Timestamp->value => 'datetime'][$category->value] ?? throw new InvalidStructure('A temporal literal is a DATE, TIME or TIMESTAMP.');
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, $type), Nullability::NotNull), $source);
    }

    /**
     * Identifies a constant.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Literal;
    }

    /**
     * @return list<Expression> A constant has no operands
     */
    #[Override]
    public function inputs(): array
    {
        return [];
    }

    /**
     * Returns the keyword followed by the string.
     */
    #[Override]
    public function spelling(): string
    {
        return strtoupper($this->category->value) . ' ' . $this->text->text;
    }

    /**
     * Preserves the facts derived from the temporal category.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Temporal literal facts are derived from its category.');
        }
        return new static($this->source, $this->category, $this->text);
    }
}
