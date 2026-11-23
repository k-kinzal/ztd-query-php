<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter\RenameTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename as ParsedRename;

/**
 * Routes changes to a resolved persistent relation to their operation owners.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RelationChangeLowering
{
    /**
     * Publishes the checked removal or rename.
     */
    public function lower(BoundStatement $operation, ParsedDrop|ParsedRename $statement): DropTable|DropView|RenameColumn|RenameTable
    {
        return $statement instanceof ParsedDrop
            ? (new DropRelationLowering())->lower($operation, $statement)
            : (new RenameLowering())->lower($operation, $statement);
    }
}
