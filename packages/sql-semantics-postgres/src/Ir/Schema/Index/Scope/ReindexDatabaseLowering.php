<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;

/**
 * Resolves a database-wide selector to its exact current database.
 *
 * @visibility SqlSemantics
 */
final class ReindexDatabaseLowering
{
    /**
     * Publishes the checked application or system index rebuild.
     */
    public function lower(BoundStatement $operation, Reindex $statement): ReindexDatabase|ReindexSystemCatalogs
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive, 'REINDEX requires the native PostgreSQL database profile.');
        Check::input($statement->target === ReindexTarget::Database || $statement->target === ReindexTarget::System, 'REINDEX requires a database or system selector.');
        $database = $context->currentDatabase;
        Check::input($database !== null, 'A database-wide rebuild requires a declared current database.');
        $written = $statement->object;
        Check::input($written === null || ($written instanceof Name && $written->value === $database->value), 'A database-wide rebuild must select the declared current database.');
        Check::input(!$statement->namesTablespace(), 'A complete REINDEX cannot move indexes to a tablespace.');
        $name = new Identifier($database->value);
        $concurrent = $statement->requestsConcurrentBuild();
        if ($statement->target === ReindexTarget::System) {
            Check::input(!$concurrent, 'A system catalog rebuild requires the standard mode.');

            return new ReindexSystemCatalogs($name, $profile->grammar, $statement->reportsProgress());
        }

        return new ReindexDatabase(
            $name,
            $concurrent ? IndexRebuildMode::Concurrent : IndexRebuildMode::Standard,
            $profile->grammar,
            $statement->reportsProgress(),
        );
    }
}
