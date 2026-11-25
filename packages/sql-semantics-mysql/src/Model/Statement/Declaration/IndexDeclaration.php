<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Declaration;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
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
     * @var list<Name> Consecutive leading full fields of the native key
     */
    public readonly array $leadingFields;

    /**
     * Stores the native name, leading fields and clustered key policy.
     * @param list<Name> $leadingFields Ordered full fields before the first expression or prefix field
     */
    public function __construct(
        public readonly Name $name,
        public readonly PhysicalIndexKind $kind,
        public readonly bool $wholeFields,
        public readonly bool $clusteredUnique = false,
        public readonly ?Name $autoIncrementField = null,
        array $leadingFields = [],
        public readonly bool $clusterCandidate = false,
    ) {
        $this->leadingFields = Check::listOf($leadingFields, Name::class, 'An index has ordered leading full fields.');
    }
}
