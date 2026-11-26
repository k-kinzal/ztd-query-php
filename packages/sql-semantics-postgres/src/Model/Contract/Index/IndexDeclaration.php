<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A named index in an analysis catalog with its physical ownership.
 *
 * @visibility SqlSemantics
 */
final class IndexDeclaration
{
    use Snapshot;

    /**
     * Records the nominal index and how the table declaration owns it.
     */
    public function __construct(
        public readonly Name $schema,
        public readonly Name $index,
        public readonly Name $table,
        public readonly IndexDeclarationKind $kind,
    ) {
    }
}
