<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\FieldConstants;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Detects a predicate that cannot be true for an unsigned integer field.
 * This is a row-elimination fact, not a replacement for nullable scalar results.
 *
 * @visibility MySqlMemory
 */
final class UnsignedBounds
{
    /**
     * @param Compiler $compiler The resolved column domains and language release
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * An unsigned field cannot equal a negative numeric literal, including when its value is NULL.
     * Modern MySQL skips materializing a system table for this impossible predicate.
     */
    public function impossible(Scalar $node): bool
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        if ($this->compiler->settings->legacy() || !$node instanceof Comparison || !in_array($node->operator, [ComparisonOperator::Equal, ComparisonOperator::NullSafeEqual], true)) {
            return false;
        }
        $fields = new FieldConstants($this->compiler);
        foreach ([[$node->left, $node->right], [$node->right, $node->left]] as [$column, $literal]) {
            if (!$column instanceof Scalar || !$literal instanceof Scalar) {
                continue;
            }
            $number = $fields->number($literal);
            if ($fields->column($column) === null || $number === null || (float) $number >= 0) {
                continue;
            }
            $domain = $this->compiler->domain($column);
            if ($domain->kind === Kind::Integer && $domain->unsigned) {
                return true;
            }
        }

        return false;
    }
}
