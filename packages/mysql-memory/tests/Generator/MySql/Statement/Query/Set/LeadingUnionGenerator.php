<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples LeadingUnion arguments within its declared cross-field constructor invariants.
 */
final class LeadingUnionGenerator implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        $width = $choices->integer(1, 3);
        $projection = new \Tests\Generator\Context\Projection();
        return new Model($projection->sample($choices, $width), $choices->one([null, ...\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier::cases()]), $projection->sample($choices, $width, true));
    }
}
