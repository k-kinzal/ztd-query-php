<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;

/**
 * Resolves the application namespace used by a PostgreSQL schema rebuild.
 *
 * @visibility SqlSemantics
 */
final class ReindexSchemaLowering
{
    /**
     * Publishes a checked namespace and its effective concurrency policy.
     */
    public function lower(BoundStatement $operation, Reindex $statement): ReindexSchema
    {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input($profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive, 'REINDEX SCHEMA requires the native PostgreSQL index profile.');
        Check::input($statement->target === ReindexTarget::Schema && $statement->object instanceof Name, 'REINDEX SCHEMA needs one schema name.');
        foreach ($statement->options as $option) {
            Check::input($option->option() === 'concurrently', 'A complete REINDEX SCHEMA accepts only concurrency options.');
        }
        Check::input((new IndexBinding())->known($context, $statement->object), 'REINDEX SCHEMA requires a declared schema.');

        return new ReindexSchema(
            new Identifier($statement->object->value),
            $statement->requestsConcurrentBuild() ? IndexRebuildMode::Concurrent : IndexRebuildMode::Standard,
            $profile->grammar,
        );
    }
}
