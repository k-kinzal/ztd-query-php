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
 * A MySQL string, hexadecimal or bit literal written after a character set introducer such as `_utf8mb4`.
 * @visibility public
 * @example Reading the introducer and the literal it applies to
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT _latin1 X'4D7953514C'");
 *     $literal = $query->outputs[0]->expression;
 *     $literal->characterSet // => 'latin1'
 *     $literal->literal->text // => "X'4D7953514C'"
 *     $literal->spelling() // => "_latin1 X'4D7953514C'"
 */
final class IntroducedLiteral extends Expression
{
    /**
     * Derives a non-NULL character string, or a binary string for the `binary` character set.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly string $characterSet, public readonly Literal $literal)
    {
        if ($literal->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Character set introducers require MySQL.');
        }
        if (preg_match('/^[a-z0-9_]+$/D', $characterSet) !== 1) {
            throw new InvalidStructure('A character set introducer names one lowercase character set.');
        }
        if (!in_array($literal->literalKind, [LiteralKind::Text, LiteralKind::Binary, LiteralKind::BitString], true)) {
            throw new InvalidStructure('A character set introducer applies to a string, hexadecimal or bit literal.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, $characterSet === 'binary' ? 'blob' : 'text'), Nullability::NotNull), $source);
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
     * Returns the introducer followed by the literal spelling.
     */
    #[Override]
    public function spelling(): string
    {
        return '_' . $this->characterSet . ' ' . $this->literal->text;
    }

    /**
     * Preserves the facts derived from the introducer.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Introduced literal facts are derived from its character set.');
        }
        return new static($this->source, $this->characterSet, $this->literal);
    }
}
