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
use SqlSemantics\Type\TypeDescriptor;

/**
 * MySQL `WEIGHT_STRING(str, result_length, codepoints, flags)`, the internal form that states the result length, the
 * padding length in characters, and the collation flags as numbers.
 * @visibility public
 * @example Reading the numeric arguments
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT WEIGHT_STRING('ab', 0, 3, 64)");
 *     $weights = $query->outputs[0]->expression;
 *     [$weights->resultLength, $weights->codepoints, $weights->flags] // => [0, 3, 64]
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => "SELECT WEIGHT_STRING('ab', 0, 3, 64)"
 */
final class InternalWeightString extends Expression
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $operand, public readonly int $resultLength, public readonly int $codepoints, public readonly int $flags)
    {
        if ($operand->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('WEIGHT_STRING requires MySQL.');
        }
        if ($resultLength < 0 || $codepoints < 0 || $flags < 0) {
            throw new InvalidStructure('WEIGHT_STRING takes unsigned numeric arguments.');
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'longblob'), $operand->nullability, $operand->nullExtendedBy), $source);
    }

    /**
     * Identifies a function of its operand.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Function;
    }

    /**
     * @return list<Expression> The string whose weights are computed
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand];
    }

    /**
     * Returns the fixed function name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'WEIGHT_STRING';
    }

    /**
     * Preserves the facts derived from the operand.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('WEIGHT_STRING facts are derived from its operand.');
        }
        return new static($this->source, $this->operand, $this->resultLength, $this->codepoints, $this->flags);
    }
}
