<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropRelationLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename as ParsedRename;
use SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable as ParsedTruncateTable;

/**
 * Routes changes to a resolved persistent relation to their operation owners.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RelationChangeLowering
{
    /**
     * Publishes the checked removal, rename or truncation.
     */
    public function lower(BoundStatement $operation, ParsedDrop|ParsedRename|ParsedTruncateTable $statement): DropIndex|DropTable|DropView|RenameColumn|RenameTable|TruncateTable
    {
        return match (true) {
            $statement instanceof ParsedDrop => (new DropRelationLowering())->lower($operation, $statement),
            $statement instanceof ParsedRename => (new RenameLowering())->lower($operation, $statement),
            $statement instanceof ParsedTruncateTable => (new TruncateLowering())->lower($operation, $statement),
        };
    }
}
