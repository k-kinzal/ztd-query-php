<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The physical name and policy derived from a MySQL table declaration.
 *
 * @visibility SqlSemantics
 */
final class IndexDeclaration
{
    use Snapshot;

    /**
     * Stores the native name, ordinary-part status and implicit clustered role.
     */
    public function __construct(
        public readonly Name $name,
        public readonly PhysicalIndexKind $kind,
        public readonly bool $wholeFields,
        public readonly bool $clusteredUnique = false,
        public readonly ?Name $autoIncrementField = null,
    ) {
    }
}
