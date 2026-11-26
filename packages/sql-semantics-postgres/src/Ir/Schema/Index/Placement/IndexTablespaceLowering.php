<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Reindex;

/**
 * Resolves the effective placement against the declared cluster coordinates.
 *
 * @visibility SqlSemantics
 */
final class IndexTablespaceLowering
{
    /**
     * Resolves the last validated option before the completed rebuild is published.
     */
    public function resolve(Reindex $statement, AnalysisContext $context): ?IndexTablespace
    {
        $name = $statement->tablespace();
        if ($name === null) {
            return null;
        }
        $target = new IndexTablespace(new Identifier($name));
        Check::input($context->knowsTablespace($name), 'The index rebuild destination requires a declared tablespace.');

        return $target;
    }
}
