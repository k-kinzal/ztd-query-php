<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call\Window;

use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;

/**
 * Describes fixed window constraints without evaluating offsets or deciding execution eligibility.
 *
 * Boundary kinds have a language order. Numeric offsets within the same kind
 * can describe an empty frame and are not compared here. A window ordering
 * accepts expressions, but not the integer-position shorthand of query ORDER BY.
 * Verified with MySQL 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Constraints
{
    /**
     * Finds a fixed window contradiction without resolving inherited names or offsets.
     */
    public function problem(\SqlSemantics\Statement\Identifier\Name|\SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification|null $window): ?\SqlSemantics\Statement\Fact\Diagnostic
    {
        if (!$window instanceof WindowSpec) {
            return null;
        }
        if ($this->positional($window) || $this->reversed($window)) {
            return new \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowConstraint($window, $this->positional($window));
        }
        $limit = match (true) {
            $window->frame?->unit === \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Groups => \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::GroupsUnit,
            $window->frame?->exclusion !== null => \SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::Exclusion,
            default => null,
        };
        return $limit === null ? null : new \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing($limit, $window);
    }

    /**
     * Identifies a positional item even when parentheses enclose the integer.
     */
    public function positional(WindowSpec $window): bool
    {
        foreach ([...$window->partition, ...$window->order] as $item) {
            $value = $item->expression;
            while ($value instanceof Grouped) {
                $value = $value->operand;
            }
            if ($value instanceof NumberLiteral && ctype_digit($value->text)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks only the relative kinds of fixed boundaries, not numeric offsets.
     */
    public function reversed(WindowSpec $window): bool
    {
        if ($window->frame === null) {
            return false;
        }
        $positions = array_flip(array_map(static fn (FrameBoundKind $kind): string => $kind->value, [FrameBoundKind::UnboundedPreceding, FrameBoundKind::Preceding, FrameBoundKind::CurrentRow, FrameBoundKind::Following, FrameBoundKind::UnboundedFollowing]));
        return $positions[$window->frame->start->kind->value] > $positions[($window->frame->end->kind ?? FrameBoundKind::CurrentRow)->value];
    }
}
