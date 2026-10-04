<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * One action of ALTER TABLE, such as ADD COLUMN, DROP INDEX, RENAME TO, ALGORITHM = … or ADD PARTITION.
 *
 * Mirrors PT_alter_table_action and the flags of Alter_info. The ALTER
 * TABLE statement derives each action in the scope of the changed table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 */
interface AlterCommand extends Node
{
    /**
     * Derives the parts of the action.
     *
     * @param Derivation $derivation The derivation of the ALTER TABLE statement
     * @param Environment $scope The scope whose only visible relation is the table as the statement leaves it
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void;
}
