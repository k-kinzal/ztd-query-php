<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Alter;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropRelationLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\DropTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabaseLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchemaLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSystemCatalogs;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\ChangeSchemaOwner;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\DropSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\RenameSchema;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaAlterLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ChangeOwner as ParsedChangeOwner;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename as ParsedRename;
use SqlSemantics\Platform\PostgreSql\Statement\Table\TruncateTable as ParsedTruncateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex as ParsedReindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;

/**
 * Routes changes to a resolved persistent relation to their operation owners.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RelationChangeLowering
{
    /**
     * Publishes the checked object change.
     */
    public function lower(
        BoundStatement $operation,
        ParsedDrop|ParsedReindex|ParsedRename|ParsedTruncateTable|ParsedChangeOwner $statement,
    ): ChangeSchemaOwner|DropIndex|DropSchema|DropTable|DropView|ReindexDatabase|ReindexIndex|ReindexPartitionTable|ReindexSchema|ReindexSystemCatalogs|ReindexTable|RenameColumn|RenameSchema|RenameTable|TruncateTable {
        return match (true) {
            $statement instanceof ParsedDrop => (new DropRelationLowering())->lower($operation, $statement),
            $statement instanceof ParsedChangeOwner, $statement instanceof ParsedRename && $statement->kind === ObjectKind::Schema => (new SchemaAlterLowering())->lower($operation, $statement),
            $statement instanceof ParsedRename => (new RenameLowering())->lower($operation, $statement),
            $statement instanceof ParsedTruncateTable => (new TruncateLowering())->lower($operation, $statement),
            $statement instanceof ParsedReindex => match ($statement->target) {
                ReindexTarget::Index => (new ReindexIndexLowering())->lower($operation, $statement),
                ReindexTarget::Table => (new ReindexTableLowering())->lower($operation, $statement),
                ReindexTarget::Schema => (new ReindexSchemaLowering())->lower($operation, $statement),
                ReindexTarget::System, ReindexTarget::Database => (new ReindexDatabaseLowering())->lower($operation, $statement),
            },
        };
    }
}
