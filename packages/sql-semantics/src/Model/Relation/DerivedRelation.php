<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**

 * DerivedRelation identifies its data source with mandatory semantic operands. @visibility public

 */
final class DerivedRelation extends \SqlSemantics\Model\TableUse
{
    /**
     * @param list<string> $columnAliases
     * @visibility SqlSemantics
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        ?string $alias,
        \SqlParser\Parser\Node $source,
        public readonly \SqlSemantics\Model\BoundQuery $query,
        public readonly array $columnAliases,
        public readonly bool $lateral,
    ) {
        \SqlSemantics\Model\Validation\Collections::strings($columnAliases);
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
    }

    #[Override]
    public function resultExpressions(): array
    {
        return array_map(static fn ($output): \SqlSemantics\Model\Expression => $output->expression, $this->query->resultColumns());
    }

    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->alias, $this->source, $this->query, $this->columnAliases, $this->lateral);
    }
}
