<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Identifies a storage reference beneath ordered array and record access operations.
 *
 * @visibility SqlSemantics
 */
final class Destination
{
    /**
     * Returns the referenced column while rejecting computed storage destinations.
     *
     * @throws InvalidStructure
     */
    public static function column(Expression $value): Expression
    {
        while (in_array($value->kind, [ExpressionKind::Subscript, ExpressionKind::Field], true)) {
            if ($value->inputs() === []) {
                throw new InvalidStructure('A storage access requires a base reference.');
            }
            $value = $value->inputs()[0];
        }
        if (!in_array($value->kind, [ExpressionKind::Column, ExpressionKind::UnresolvedColumn], true)) {
            throw new InvalidStructure('A storage destination must reference a column.');
        }
        return $value;
    }
}
