<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A named index in an analysis catalog with its owner and removal eligibility.
 *
 * @visibility SqlSemantics
 */
final class IndexDeclaration
{
    use Snapshot;

    /**
     * Records the nominal index coordinate and whether it can participate in ordinary removal.
     */
    public function __construct(
        public readonly Name $schema,
        public readonly Name $index,
        public readonly Name $table,
        public readonly bool $ordinary,
    ) {
    }
}
