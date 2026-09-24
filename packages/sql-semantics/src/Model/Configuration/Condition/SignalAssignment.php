<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Condition;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Reference\UnresolvedVariableReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Scalar\Value\TemporalLiteral;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One SET item of SIGNAL or RESIGNAL: a condition item and the literal or variable it receives.
 * @visibility public
 * @example Reading a signaled message
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'stop'");
 *     [$statement->assignments[0]->item->value, $statement->assignments[0]->value->text] // => ['MESSAGE_TEXT', "'stop'"]
 */
final class SignalAssignment
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly ConditionItem $item, public readonly Literal|IntroducedLiteral|TemporalLiteral|VariableReference|UnresolvedVariableReference $value)
    {
        if (!$item->signalable()) {
            throw new InvalidStructure('SIGNAL cannot assign the returned SQLSTATE.');
        }
        if ($value->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('A signal item value must use MySQL.');
        }
        if ($value instanceof Literal && $value->literalKind === LiteralKind::Null) {
            throw new InvalidStructure('A signal item cannot be set to NULL.');
        }
    }

    /**
     * Assigns another value to the same item.
     * @throws InvalidStructure
     */
    public function withValue(Literal|IntroducedLiteral|TemporalLiteral|VariableReference|UnresolvedVariableReference $value): self
    {
        return new self($this->item, $value);
    }
}
