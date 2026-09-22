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

    #[Override]
    public function resultExpressions(): array
    {
        return [];
    }

    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->alias, $this->source, $this->target);
    }
}
