<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**

 * CteReference identifies its data source with mandatory semantic operands. @visibility public

 */
final class CteReference extends \SqlSemantics\Model\TableUse
{
    /**
     * CTE name visible at the reference site.
     */
    public readonly string $name;

    /**
     * @visibility SqlSemantics
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        ?string $alias,
        \SqlParser\Parser\Node $source,
        public readonly \SqlSemantics\Model\Query\CommonTableExpression $definition,
    ) {
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
        $this->name = $definition->name;
    }

    /**
     * Returns the ordered value expressions exposed by this relation.
     * @return list<\SqlSemantics\Model\Expression>
     */
    #[Override]
    public function resultExpressions(): array
    {
        return array_map(static fn ($output): \SqlSemantics\Model\Expression => $output->expression, $this->definition->query->resultColumns());
    }

    /**
     * Returns a new relation occurrence in the supplied scope, retaining its source and operands.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->alias, $this->source, $this->definition);
    }
}
