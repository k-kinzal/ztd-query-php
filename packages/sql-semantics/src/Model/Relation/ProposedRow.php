<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**

 * ProposedRow identifies its data source with mandatory semantic operands. @visibility public

 */
final class ProposedRow extends \SqlSemantics\Model\TableUse
{
    /**
     * @visibility SqlSemantics
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        ?string $alias,
        \SqlParser\Parser\Node $source,
        public readonly \SqlSemantics\Model\TableUse $target,
    ) {
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
    }

    /**
     * Returns the ordered value expressions exposed by this relation.
     * @return list<\SqlSemantics\Model\Expression>
     */
    #[Override]
    public function resultExpressions(): array
    {
        return [];
    }

    /**
     * Returns a new relation occurrence in the supplied scope, retaining its source and operands.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->alias, $this->source, $this->target);
    }
}
