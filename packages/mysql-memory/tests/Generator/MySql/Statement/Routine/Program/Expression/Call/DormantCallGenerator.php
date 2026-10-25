<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples DormantCall arguments within its declared cross-field constructor invariants.
 */
final class DormantCallGenerator implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        return new Model(new \SqlSemantics\Statement\Identifier\Name($choices->one(['ABS', 'abs', 'SQRT', 'sqrt', 'POW', 'pow'])));
    }
}
