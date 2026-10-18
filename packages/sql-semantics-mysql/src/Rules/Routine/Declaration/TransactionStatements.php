<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;

/**
 * Identifies DDL instructions excluded from function and trigger bodies.
 *
 * Temporary CREATE/DROP TABLE remain legal body instructions. Storage DDL,
 * permanent table DDL, database DDL and account DDL are refused even in an
 * unreachable branch. SET DEFAULT ROLE and INSTALL COMPONENT do not have this
 * declaration restriction. Observed through SQL on MySQL 5.7, 8.0, 8.4 and 9.1.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class TransactionStatements
{
    /**
     * Answers whether the instruction introduces a forbidden implicit transaction boundary.
     */
    public function implicit(Node $statement): bool
    {
        if ($statement instanceof M\Table\CreateTable || $statement instanceof M\Table\CreateTableLike || $statement instanceof M\Routine\Program\Write\DeferredCreateTable) {
            return $statement->temporaryWords === 0;
        }
        if ($statement instanceof M\Alter\DropTable) {
            return !$statement->temporary;
        }
        if ($statement instanceof M\Account\Privilege\PrivilegeChange) {
            return true;
        }
        return in_array($statement::class, [
            M\Alter\AlterTable::class, M\Routine\Program\Write\DeferredAlterTable::class,
            M\Alter\RenameTable::class, M\Alter\TruncateTable::class, M\Alter\DropIndex::class, M\Table\CreateIndex::class,
            M\Server\Database\CreateDatabase::class, M\Server\Database\AlterDatabase::class, M\Server\Database\DropDatabase::class,
            M\Server\Storage\CreateTablespace::class, M\Server\Storage\AlterTablespace::class, M\Server\Storage\DropTablespace::class,
            M\Server\Storage\AlterTablespaceDatafile::class, M\Server\Storage\AlterTablespaceAccess::class, M\Server\Storage\RenameTablespace::class,
            M\Server\Storage\CreateUndoTablespace::class, M\Server\Storage\AlterUndoTablespace::class, M\Server\Storage\DropUndoTablespace::class,
            M\Server\Storage\CreateLogfileGroup::class, M\Server\Storage\AlterLogfileGroup::class, M\Server\Storage\DropLogfileGroup::class,
            M\Account\CreateUser::class, M\Account\AlterUser::class, M\Account\DropUser::class, M\Account\RenameUser::class,
            M\Account\CreateRole::class, M\Account\DropRole::class,
            M\Server\ForeignServer\CreateServer::class, M\Server\ForeignServer\AlterServer::class, M\Server\ForeignServer\DropServer::class,
            M\Server\Plugin\InstallPlugin::class, M\Server\Plugin\UninstallPlugin::class,
        ], true);
    }
}
