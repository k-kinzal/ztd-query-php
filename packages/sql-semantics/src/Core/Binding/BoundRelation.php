<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Binding;

use SqlSemantics\Core\Model\Join;
use SqlSemantics\Core\Model\TableUse;

/**
 * A relation and the namespace visible above it.
 *
 * @visibility SqlSemantics
 */
final class BoundRelation
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly TableUse|Join $relation, public readonly Scope $scope)
    {
    }
}
