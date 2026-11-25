<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression\Choice;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A signed equality candidate and the corresponding CASE result.
 *
 * @visibility public
 * @example Constructing a simple CASE arm
 *     $null = new \SqlSemantics\Platform\MySql\Ir\NullLiteral();
 *     (new \SqlSemantics\Platform\MySql\Ir\Expression\Choice\SimpleCaseArm($null, $null))->result === $null // => true
 */
final class SimpleCaseArm
{
    use Snapshot;

    /**
     * Pairs a checked signed equality candidate with one result.
     */
    public function __construct(
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral $decision,
        public readonly BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|SignedCase $result,
    ) {
    }
}
