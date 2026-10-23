<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch as Model;
use Tests\Generator\Choices;
use Tests\Generator\Inhabitant;

/**
 * Samples RedoLogSwitch arguments within its declared cross-field constructor invariants.
 */
final class RedoLogSwitch implements Inhabitant
{
    /**
     * Constructs the selected semantic type directly from generated operands.
     */
    public function sample(Choices $choices): Model
    {
        return new Model($choices->integer(0, 1) === 1, new \SqlSemantics\Statement\Identifier\Name($choices->one(['INNODB', 'innodb', 'InnoDB'])), new \SqlSemantics\Statement\Identifier\Name($choices->one(['REDO_LOG', 'redo_log', 'Redo_Log'])));
    }
}
