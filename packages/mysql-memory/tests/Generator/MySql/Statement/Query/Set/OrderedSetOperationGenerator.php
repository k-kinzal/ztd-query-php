<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Set;

use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples OrderedSetOperation arguments within its declared cross-field constructor invariants.
 */
final class OrderedSetOperationGenerator implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        $width = $choices->integer(1, 3);
        $projection = new \Tests\Generator\Context\Projection();
        return new Model($projection->sample($choices, $width), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, $choices->one([null, ...\SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier::cases()]), $projection->sample($choices, $width, true), limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $choices->integer(0, 10))));
    }
}
