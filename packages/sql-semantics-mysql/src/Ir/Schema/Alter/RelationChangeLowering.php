<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Schema\DropTable;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\MySql\Ir\Schema\View\DropViewLowering;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable as ParsedAlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex as ParsedDropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable as ParsedDropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable as ParsedRenameTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable as ParsedTruncateTable;
use SqlSemantics\Platform\MySql\Statement\View\DropView as ParsedDropView;

/**
 * Routes MySQL relation changes through their individual boundaries.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RelationChangeLowering
{
    /**
     * Identifies the parsed operations routed through the relation change boundary.
     * @phpstan-assert-if-true ParsedAlterTable|ParsedDropIndex|ParsedDropTable|ParsedDropView|ParsedRenameTable|ParsedTruncateTable $statement
     */
    public function applies(Statement $statement): bool
    {
        return $statement instanceof ParsedDropIndex || $statement instanceof ParsedDropTable || $statement instanceof ParsedDropView
            || $statement instanceof ParsedRenameTable || $statement instanceof ParsedTruncateTable
            || ($statement instanceof ParsedAlterTable && (new AlterIndexRemovalLowering())->removal($statement) !== null);
    }

    /**
     * Publishes the specific operation after its relation has been bound.
     */
    public function lower(BoundStatement $operation, ParsedAlterTable|ParsedDropIndex|ParsedDropTable|ParsedDropView|ParsedRenameTable|ParsedTruncateTable $statement): DropIndex|DropTable|DropView|RenameTable|TruncateTable
    {
        return match (true) {
            $statement instanceof ParsedAlterTable => (new AlterIndexRemovalLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropIndex => (new DropIndexLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropTable => (new DropTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropView => (new DropViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedRenameTable => (new TableRenameLowering())->lower($operation, $statement),
            $statement instanceof ParsedTruncateTable => (new TruncateLowering())->lower($operation, $statement),
        };
    }
}
