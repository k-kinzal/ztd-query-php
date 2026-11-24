<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespaceLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\ReindexTarget;

/**
 * Resolves a single-index rebuild to its declared physical or partitioned target.
 *
 * @visibility SqlSemantics
 */
final class ReindexIndexLowering
{
    /**
     * Binds the target and keeps the effective native concurrency request.
     */
    public function lower(BoundStatement $operation, Reindex $statement): ReindexIndex
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->relationNames === Comparison::Sensitive,
            'REINDEX INDEX requires the native PostgreSQL index profile.'
        );
        Check::input(
            $statement->target === ReindexTarget::Index && $statement->object instanceof QualifiedName,
            'A complete REINDEX needs a named index target.'
        );
        $tablespace = (new IndexTablespaceLowering())->resolve($statement, $context);
        return new ReindexIndex(
            (new IndexTargetLowering())->resolveRebuild($context, $statement->object),
            $statement->requestsConcurrentBuild() ? IndexRebuildMode::Concurrent : IndexRebuildMode::Standard,
            $context->profile->grammar,
            $statement->reportsProgress(),
            $tablespace,
        );
    }
}
