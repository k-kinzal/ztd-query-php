<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator\Comparison;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * [NOT] IN with a list: whether a value equals one of the elements.
 *
 * The result is NULL when the value is NULL, or when no element equals it and one is NULL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#operator_in.
 *
 * @visibility MySqlMemory
 */
final class Membership implements Evaluable
{
    /**
     * @param Evaluable $operand The value tested
     * @param list<array{Evaluable, Comparator}> $elements The elements, each with how the value compares with it
     * @param bool $negated Whether NOT is written
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly Evaluable $operand, public readonly array $elements, public readonly bool $negated, public readonly Domain $domain)
    {
    }

    /**
     * Answers the domain of the truth value.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Tests the value for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            return null;
        }
        $unknown = false;
        foreach ($this->elements as [$element, $comparator]) {
            $order = $comparator->compare($value, $element->evaluate($frame), $frame->context);
            if ($order === 0) {
                return $this->negated ? 0 : 1;
            }
            $unknown = $unknown || $order === null;
        }

        return $unknown ? null : ($this->negated ? 1 : 0);
    }
}
