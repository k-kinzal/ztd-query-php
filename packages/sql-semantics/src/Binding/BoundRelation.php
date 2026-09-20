<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlSemantics\Model\Join;
use SqlSemantics\Model\TableUse;

/**
 * A relation and the namespace visible above it.
 *
 * @visibility SqlSemantics
 */
final class BoundRelation
{
    /**
     * Binds the dependencies used for this analysis.
     */
    public function __construct(public readonly TableUse|Join $relation, public readonly Scope $scope)
    {
    }
}
