<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;

/**
 * A transient physical index proposal before native name allocation.
 * @visibility SqlSemantics
 */
final class IndexCandidate
{
    use Snapshot;

    /**
     * @var list<array{Name, bool}> Ordered full leading fields and their native DESC flags
     */
    public readonly array $prefix;

    /**
     * Records native key attributes before resolving the final name and clustered role.
     * @param list<array{Name, bool}> $prefix Full leading fields and their ordering
     */
    public function __construct(
        public readonly IndexKind $kind,
        public readonly ?Name $writtenName,
        public readonly ?Name $firstField,
        public readonly bool $wholeFields,
        public readonly bool $clusterCandidate,
        public readonly ?Name $autoIncrementField,
        array $prefix,
    ) {
        $checked = [];
        foreach ($prefix as [$field, $descending]) {
            $checked[] = [$field, $descending];
        }
        $this->prefix = $checked;
    }

    /**
     * Creates the settled catalog entry for the exact allocated physical name.
     */
    public function declaration(Name $name): IndexDeclaration
    {
        return new IndexDeclaration(
            $name,
            PhysicalIndexKind::from($this->kind->value),
            $this->wholeFields,
            false,
            in_array($this->kind, [IndexKind::Primary, IndexKind::Unique, IndexKind::Index], true) ? $this->autoIncrementField : null,
            array_column($this->prefix, 0),
            $this->clusterCandidate,
        );
    }
}
