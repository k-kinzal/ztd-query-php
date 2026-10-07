<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Operator;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use Override;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;

/**
 * AND, OR and XOR in three-valued logic.
 *
 * AND is 0 when an operand is false, else NULL when one is NULL; OR is 1 when an operand is
 * true, else NULL when one is NULL; XOR is NULL when either is NULL. The right operand is not
 * evaluated when the left one decides.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/logical-operators.html.
 *
 * @visibility MySqlMemory
 */
final class Logic implements Evaluable
{
    /**
     * @param LogicalOperator $operator The operator
     * @param Evaluable $left The left operand
     * @param Evaluable $right The right operand
     * @param Domain $domain The domain of the truth value
     */
    public function __construct(public readonly LogicalOperator $operator, public readonly Evaluable $left, public readonly Evaluable $right, public readonly Domain $domain)
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
     * Combines the truth values of the operands for a row.
     */
    #[Override]
    public function evaluate(Frame $frame): ?int
    {
        $left = Convert::toBool($this->left->evaluate($frame), $this->left->domain(), $frame->context);
        if ($this->operator === LogicalOperator::And && $left === false) {
            return 0;
        }
        if ($this->operator === LogicalOperator::Or && $left === true) {
            return 1;
        }
        $right = Convert::toBool($this->right->evaluate($frame), $this->right->domain(), $frame->context);

        return match ($this->operator) {
            LogicalOperator::And => $right === false ? 0 : ($left === null || $right === null ? null : 1),
            LogicalOperator::Or => $right === true ? 1 : ($left === null || $right === null ? null : 0),
            LogicalOperator::Xor => $left === null || $right === null ? null : ($left !== $right ? 1 : 0),
        };
    }
}
