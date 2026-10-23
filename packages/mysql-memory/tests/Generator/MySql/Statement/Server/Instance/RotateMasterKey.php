<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples RotateMasterKey arguments within its declared cross-field constructor invariants.
 */
final class RotateMasterKey implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        return new Model(new \SqlSemantics\Statement\Identifier\Name($choices->one(['INNODB', 'innodb', 'InnoDB', 'BINLOG', 'binlog', 'Binlog'])));
    }
}
