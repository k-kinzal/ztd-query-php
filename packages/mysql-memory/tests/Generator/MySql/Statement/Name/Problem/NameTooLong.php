<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Name\Problem;

use SqlSemantics\Platform\MySql\Statement\Name\Problem\NameTooLong as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples NameTooLong arguments within its declared cross-field constructor invariants.
 */
final class NameTooLong implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        $name = str_repeat($choices->one(['a', 'É', '猫']), $choices->integer(65, 128));
        return new Model(new \SqlSemantics\Statement\Identifier\Name($name), $choices->one([null, ...\SqlSemantics\Platform\MySql\Statement\Name\ObjectNameKind::cases()]));
    }
}
