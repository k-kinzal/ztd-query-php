<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Predicate;

use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Native signed membership with three-valued equality and explicit negation.
 *
 * @visibility public
 * @example Keeping the alternative order
 *     count((new \SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership(new \SqlSemantics\Platform\MySql\Ir\NullLiteral(), [new \SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral('2')]))->alternatives) // => 1
 */
final class IntegerMembership
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral> The ordered alternatives
     */
    public readonly array $alternatives;

    /**
     * Closes the membership operands and detaches the ordered choices from the caller.
     * @param array<array-key, mixed> $alternatives The right-hand list in SQL order
     */
    public function __construct(
        public readonly ColumnReference|IntegralColumnReference|RangeIntegerLiteral|NullLiteral $subject,
        array $alternatives,
        public readonly bool $negated = false,
    ) {
        Check::input(array_is_list($alternatives) && $alternatives !== [] && count($alternatives) <= 8190, 'Signed membership requires an ordered list of one to 8190 alternatives.');
        $terms = [];
        foreach ($alternatives as $alternative) {
            Check::input(
                $alternative instanceof ColumnReference || $alternative instanceof IntegralColumnReference
                    || $alternative instanceof RangeIntegerLiteral || $alternative instanceof NullLiteral,
                'A membership alternative must be a pure signed integer or NULL.',
            );
            $terms[] = $alternative;
        }
        $this->alternatives = $terms;
    }
}
