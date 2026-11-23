<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Schema\DropTable;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropViewLowering;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable as ParsedDropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable as ParsedRenameTable;
use SqlSemantics\Platform\MySql\Statement\View\DropView as ParsedDropView;

/**
 * Routes changes to persistent MySQL relations through their individual boundaries.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RelationChangeLowering
{
    /**
     * Publishes the checked removal or ordered name change.
     */
    public function lower(BoundStatement $operation, ParsedDropTable|ParsedDropView|ParsedRenameTable $statement): DropTable|DropView|RenameTable
    {
        return match (true) {
            $statement instanceof ParsedDropTable => (new DropTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropView => (new DropViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedRenameTable => (new TableRenameLowering())->lower($operation, $statement),
        };
    }
}
