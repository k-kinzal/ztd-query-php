<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**

 * TableReference identifies its data source with mandatory semantic operands. @visibility public

 */
final class TableReference extends \SqlSemantics\Model\TableUse
{
    /**
     * @visibility SqlSemantics
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        public readonly QualifiedName $name,
        ?string $alias,
        \SqlParser\Parser\Node $source,
    ) {
        if (count($name->parts) > 2 || $name->parts[count($name->parts) - 1] !== $declaration->name || (count($name->parts) === 2 && $name->parts[0] !== $declaration->schema)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A table reference name must identify its declaration.');
        }
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
        return new static($this->id, $scopeId, $this->declaration, $this->name, $this->alias, $this->source);
    }
}
