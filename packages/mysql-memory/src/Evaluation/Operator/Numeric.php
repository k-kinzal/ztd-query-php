<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;

/**
 * An operand a comparison or a truth test reads as a double, read when it is evaluated.
 *
 * An operand constant for the statement is read once: the first time it is needed, and every
 * later row of the statement sees the same double, so a string that does not spell a number
 * warns once for the statement. An operand that varies by row is read for each row.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 *
 * @visibility MySqlMemory
 */
final class Numeric implements Evaluable
{
    /**
     * The domain of the double: nullable as the operand is.
     */
    public readonly Domain $domain;

    /**
     * @param Evaluable $operand The operand read
     * @param bool $once Whether the operand is constant for the statement, so it is read once
     */
    public function __construct(public readonly Evaluable $operand, public readonly bool $once)
    {
        $this->domain = Domain::double()->withNullable($operand->domain()->nullable);
    }

    /**
     * Answers the domain of the double.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->domain;
    }

    /**
     * Reads the operand as a double, or answers the double read before for a constant operand.
     */
    #[Override]
    public function evaluate(Frame $frame): ?float
    {
        if (!$this->once) {
            return Convert::toDouble($this->operand->evaluate($frame), $this->operand->domain(), $frame->context);
        }
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = [Convert::toDouble($this->operand->evaluate($frame), $this->operand->domain(), $frame->context)];
        }
        $value = $kept[$this][0];

        return $value === null ? null : (float) $value;
    }
}
