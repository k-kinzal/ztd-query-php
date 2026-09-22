<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**

 * FunctionRelation identifies its data source with mandatory semantic operands. @visibility public

 */
final class FunctionRelation extends \SqlSemantics\Model\TableUse
{
    /**
     * @param list<\SqlSemantics\Model\OutputColumn> $outputs
     * @param list<string> $columnAliases
     * @visibility SqlSemantics
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        ?string $alias,
        \SqlParser\Parser\Node $source,
        public readonly \SqlSemantics\Model\Expression $function,
        public readonly array $outputs,
        public readonly array $columnAliases,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($outputs, \SqlSemantics\Model\OutputColumn::class);
        \SqlSemantics\Model\Validation\Collections::strings($columnAliases);
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
    }

    #[Override]
    public function resultExpressions(): array
    {
        return array_map(static fn ($output): \SqlSemantics\Model\Expression => $output->expression, $this->outputs);
    }

    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->declaration, $this->alias, $this->source, $this->function, $this->outputs, $this->columnAliases);
    }
}
