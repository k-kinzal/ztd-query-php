<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\QueryComparison;

/**
 * Applies a classified MySQL interval to a required temporal input without evaluating either.
 * @visibility public
 * @example Inspecting temporal arithmetic
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY');
 *     $query->outputs[0]->expression->unit->value // => 'DAY'
 */
final class DateShift extends Expression
{
    /**
     * The date or time input, including an inferred parameter type when applicable.
     */
    public readonly Expression $value;
    /**
     * The interval quantity, whose unit determines how the consumer interprets it.
     */
    public readonly Expression $quantity;

    /**
     * Derives the result from required scalar operands and the selected release's arithmetic rules.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, Expression $value, Expression $quantity, public readonly MySqlUnit $unit, public readonly ShiftDirection $direction, public readonly DateArithmeticRules $rules, public readonly IntervalOperandOrder $operandOrder = IntervalOperandOrder::TemporalFirst)
    {
        if ($value->type->dialect !== Dialect::MySql || $quantity->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('MySQL interval arithmetic requires MySQL operands.');
        }
        if (!in_array(QueryComparison::width($value), [null, 1], true) || !in_array(QueryComparison::width($quantity), [null, 1], true)) {
            throw new InvalidStructure('Temporal arithmetic requires scalar operands.');
        }
        if ($operandOrder === IntervalOperandOrder::IntervalFirst && $direction !== ShiftDirection::Add) {
            throw new InvalidStructure('A leading interval can only be added to its temporal input.');
        }
        $this->value = DateArithmeticFacts::parameter($value, IntervalFields::calendarOnly($unit) ? 'date' : 'datetime', $rules);
        $this->quantity = DateArithmeticFacts::parameter($quantity, IntervalFields::quantityType($unit), $rules);
        $extensions = array_values(array_unique([...$value->nullExtendedBy, ...$quantity->nullExtendedBy]));
        parent::__construct(new ExpressionFacts(DateArithmeticFacts::result($this->value, $unit, $rules), DateArithmeticFacts::nullability($this->value, $this->quantity), $extensions), $source);
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::DateShift;
    }

    /**
     * @return list<Expression> Inputs in their SQL binding order
     */
    #[Override]
    public function inputs(): array
    {
        return $this->operandOrder === IntervalOperandOrder::TemporalFirst ? [$this->value, $this->quantity] : [$this->quantity, $this->value];
    }

    /**
     * Returns the canonical operation for this shift direction.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->direction->value;
    }

    /**
     * Preserves the result facts derived from temporal operands and interval fields.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Temporal arithmetic facts are derived from its operands and release rules.');
        }
        return new self($this->source, $this->value, $this->quantity, $this->unit, $this->direction, $this->rules, $this->operandOrder);
    }
}
