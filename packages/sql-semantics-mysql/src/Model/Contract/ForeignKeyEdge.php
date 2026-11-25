<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Contract;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A native persistent child and its referenced parent in the catalog snapshot.
 * @visibility SqlSemantics
 */
final class ForeignKeyEdge
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> Ordered child fields
     */
    public readonly array $childFields;

    /**
     * @var non-empty-list<Name>|null Ordered referenced fields when settled
     */
    public readonly ?array $parentFields;

    /**
     * Holds both declaration coordinates and their ordered foreign-key fields.
     * @param non-empty-list<Name> $childFields Effective child fields
     * @param non-empty-list<Name>|null $parentFields Written parent fields
     */
    public function __construct(
        public readonly QualifiedName $child,
        public readonly QualifiedName $parent,
        array $childFields,
        ?array $parentFields,
    ) {
        Check::input(
            $child->schema !== null && $parent->schema !== null && $child->catalog === null && $parent->catalog === null,
            'A MySQL foreign key uses database-qualified child and parent declarations.'
        );
        $this->childFields = Check::listOf($childFields, Name::class, 'A foreign key has ordered child fields.', 1);
        $this->parentFields = $parentFields === null ? null : Check::listOf($parentFields, Name::class, 'A foreign key has ordered parent fields.', 1);
    }
}
