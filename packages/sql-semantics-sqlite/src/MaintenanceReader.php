<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Maintenance\Analyze;
use SqlSemantics\Statement\Maintenance\Reindex;

/**
 * Reads statistics collection and index rebuilding as distinct maintenance operations.
 * @visibility SqlSemantics
 */
final class MaintenanceReader
{
    /**
     * Preserves whether the request names an object or operates on the whole database.
     */
    public function read(Node $command): Analyze|Reindex
    {
        $tokens = $command->tokens();
        $target = Tree::child($command, ['nm']) === null ? null : (new IdentifierReader())->qualified($command);
        return match (\SqlSemantics\Statement\Identifier\Ascii::upper($tokens[0]->text)) {
            'ANALYZE' => new Analyze($target),
            'REINDEX' => new Reindex($target),
            default => Tree::unsupported($command, 'maintenance operation'),
        };
    }
}
