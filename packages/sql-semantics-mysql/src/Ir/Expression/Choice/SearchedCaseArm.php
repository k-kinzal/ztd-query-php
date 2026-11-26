<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression\Choice;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A signed truth decision and the corresponding CASE result.
 *
 * @visibility public
 * @example Constructing a searched CASE arm
 *     $null = new \SqlSemantics\Platform\MySql\Ir\NullLiteral();
 *     (new \SqlSemantics\Platform\MySql\Ir\Expression\Choice\SearchedCaseArm($null, $null))->result === $null // => true
 */
final class SearchedCaseArm
{
    use Snapshot;

    /**
     * Pairs a checked signed truth decision with one result.
     */
    public function __construct(
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|IntegerMembership $decision,
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|SignedCase $result,
    ) {
    }
}
