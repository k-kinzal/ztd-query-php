<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Subquery;

use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\RowSubquery as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples RowSubquery arguments within its declared cross-field constructor invariants.
 */
final class RowSubquery implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        return new Model((new \Tests\Generator\MySql\Projection())->sample($choices, $choices->integer(2, 4)));
    }
}
