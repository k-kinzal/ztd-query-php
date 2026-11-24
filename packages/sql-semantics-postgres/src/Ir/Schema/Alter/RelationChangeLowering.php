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
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\RevokeSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccessLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\MoveSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\ReconfigureSequenceLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\RenameSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SetSequenceLogging;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\AlterSequenceAuthorization;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceActionsLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\DropSequence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Truncate\TruncateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\DropView;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant as ParsedGrant;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke as ParsedRevoke;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ChangeOwner as ParsedChangeOwner;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop as ParsedDrop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Rename as ParsedRename;
use SqlSemantics\Platform\PostgreSql\Statement\Object\SetSchema as ParsedSetSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable as ParsedAlterTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\AlterSequence as ParsedAlterSequence;
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
        ParsedDrop|ParsedReindex|ParsedRename|ParsedTruncateTable|ParsedChangeOwner|ParsedSetSchema|ParsedAlterSequence|ParsedAlterTable|ParsedGrant|ParsedRevoke $statement,
    ): AlterSequenceAuthorization|ChangeSchemaOwner|DropIndex|DropSchema|DropSequence|DropTable|DropView|GrantSequenceAccess|MoveSequence|ReconfigureSequence|ReindexDatabase|ReindexIndex|ReindexPartitionTable|ReindexSchema|ReindexSystemCatalogs|ReindexTable|RenameColumn|RenameSchema|RenameSequence|RenameTable|RevokeSequenceAccess|SetSequenceLogging|TruncateTable {
        return match (true) {
            $statement instanceof ParsedDrop => (new DropRelationLowering())->lower($operation, $statement),
            $statement instanceof ParsedChangeOwner, $statement instanceof ParsedRename && $statement->kind === ObjectKind::Schema => (new SchemaAlterLowering())->lower($operation, $statement),
            $statement instanceof ParsedSetSchema => (new MoveSequenceLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterSequence => (new ReconfigureSequenceLowering())->lower($operation, $statement),
            $statement instanceof ParsedAlterTable => (new SequenceActionsLowering())->lower($operation, $statement),
            $statement instanceof ParsedGrant, $statement instanceof ParsedRevoke => (new SequenceAccessLowering())->lower($operation, $statement),
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
