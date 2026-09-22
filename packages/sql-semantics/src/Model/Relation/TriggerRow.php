<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Schema\TableDefinition;

/**
 * The OLD or NEW row image supplied by a trigger's subject table.
 * @visibility public
 */
final class TriggerRow extends TableUse
{
    /**
     * @visibility SqlSemantics
     */
    public function __construct(string $id, string $scopeId, TableDefinition $declaration, Node $source, public readonly RowVersion $version)
    {
        parent::__construct($id, $scopeId, $declaration, $version->value, $source);
    }

    #[Override]
    public function resultExpressions(): array
    {
        return [];
    }

    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->source, $this->version);
    }
}
