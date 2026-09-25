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
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * MySQL `WEIGHT_STRING(str [AS {CHAR | BINARY}(n)] [LEVEL ...])`: the binary sort key of a string under its
 * collation; MySQL 5.x also selects collation levels.
 * @visibility public
 * @example Reading the padding of a weight string
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SELECT WEIGHT_STRING('ab' AS CHAR(3))");
 *     $query->outputs[0]->expression->padding->length // => 3
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => "SELECT WEIGHT_STRING('ab' AS CHAR(3))"
 */
final class WeightString extends Expression
{
    /**
     * @var list<WeightLevel> MySQL 5.x LEVEL items in written order
     */
    public readonly array $levels;

    /**
     * @param list<WeightLevel> $levels
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $operand, public readonly ?WeightPadding $padding = null, array $levels = [])
    {
        if ($operand->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('WEIGHT_STRING requires MySQL.');
        }
        Collections::objects($levels, WeightLevel::class);
        if ($levels !== [] && $padding?->binary === true) {
            throw new InvalidStructure('WEIGHT_STRING ... AS BINARY has no LEVEL clause.');
        }
        if (count($levels) > 1 && array_filter($levels, static fn (WeightLevel $level): bool => $level->first !== $level->last) !== []) {
            throw new InvalidStructure('A range of weight levels is the only LEVEL item.');
        }
        $this->levels = $levels;
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
        return new static($this->source, $this->operand, $this->padding, $this->levels);
    }
}
