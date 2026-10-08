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
 * The result is NULL when the value is NULL, or when no element equals it and one is NULL. The
 * elements are evaluated in order until one equals the value; for a NULL value every element is
 * evaluated. When every element is constant and compares with the value the same way, the server
 * evaluates them all once, before the value of the first row, and looks the value up in them.
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
     * @param bool $fixed Whether the elements are evaluated once for the statement
     */
    public function __construct(public readonly Evaluable $operand, public readonly array $elements, public readonly bool $negated, public readonly Domain $domain, public readonly bool $fixed = false)
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
        $values = $this->fixed ? $this->values($frame) : null;
        $value = $this->operand->evaluate($frame);
        if ($value === null) {
            foreach ($values === null ? $this->elements : [] as [$element]) {
                $element->evaluate($frame);
            }

            return null;
        }
        $unknown = false;
        foreach ($this->elements as $index => [$element, $comparator]) {
            $order = $comparator->compare($value, $values === null ? $element->evaluate($frame) : $values[$index], $frame->context);
            if ($order === 0) {
                return $this->negated ? 0 : 1;
            }
            $unknown = $unknown || $order === null;
        }

        return $unknown ? null : ($this->negated ? 1 : 0);
    }

    /**
     * Answers the values of the elements, evaluated the first time they are needed in the statement.
     *
     * @return list<int|float|string|null>
     */
    public function values(Frame $frame): array
    {
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = array_map(static fn (array $element): int|float|string|null => $element[0]->evaluate($frame), $this->elements);
        }

        return $kept[$this];
    }
}
