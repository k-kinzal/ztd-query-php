<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Schema\DropTable;
use SqlSemantics\Platform\MySql\Ir\Schema\DropTableLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\AddForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAdditionLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\DropForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\AlterForeignKeys;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange\ForeignKeyExchangeLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SetIndexVisibility;
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
            || ($statement instanceof ParsedAlterTable && (
                (new AlterIndexRemovalLowering())->removals($statement) !== null || (new AlterIndexRenameLowering())->moves($statement) !== null
                || (new AlterMixedIndexesLowering())->changes($statement) !== null
                || (new IndexVisibilityLowering())->edits($statement) !== null
                || (new ForeignKeyRemovalLowering())->changes($statement) !== null
                || (new ForeignKeyAdditionLowering())->additions($statement) !== null
                || (new ForeignKeyExchangeLowering())->actions($statement) !== null
            ));
    }

    /**
     * Publishes the specific operation after its relation has been bound.
     */
    public function lower(BoundStatement $operation, ParsedAlterTable|ParsedDropIndex|ParsedDropTable|ParsedDropView|ParsedRenameTable|ParsedTruncateTable $statement): AddForeignKeys|AlterForeignKeys|AlterIndexes|DropForeignKeys|DropIndex|DropIndexes|DropTable|DropView|RenameIndexes|RenameTable|SetIndexVisibility|TruncateTable
    {
        return match (true) {
            $statement instanceof ParsedAlterTable && (new ForeignKeyExchangeLowering())->actions($statement) !== null
                => (new ForeignKeyExchangeLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable && (new ForeignKeyAdditionLowering())->additions($statement) !== null
                => (new ForeignKeyAdditionLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable && (new ForeignKeyRemovalLowering())->changes($statement) !== null
                => (new ForeignKeyRemovalLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable && (new IndexVisibilityLowering())->edits($statement) !== null
                => (new IndexVisibilityLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable && (new AlterMixedIndexesLowering())->changes($statement) !== null
                => (new AlterMixedIndexesLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable && (new AlterIndexRenameLowering())->moves($statement) !== null
                => (new AlterIndexRenameLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable => (new AlterIndexRemovalLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropIndex => (new DropIndexLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropTable => (new DropTableLowering())->lower($operation, $statement),
            $statement instanceof ParsedDropView => (new DropViewLowering())->lower($operation, $statement),
            $statement instanceof ParsedRenameTable => (new TableRenameLowering())->lower($operation, $statement),
            $statement instanceof ParsedTruncateTable => (new TruncateLowering())->lower($operation, $statement),
        };
    }
}
