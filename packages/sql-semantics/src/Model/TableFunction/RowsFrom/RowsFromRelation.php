<?php

declare(strict_types=1);

namespace SqlSemantics\Model\TableFunction\RowsFrom;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\TableDefinition;

/**
 * A FROM item reading a function table, with its alias and column aliases.
 * @visibility public
 * @example Reading the aliased columns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f() AS (a integer), g(1)) WITH ORDINALITY AS r(x, y, n)');
 *     array_map(static fn ($column) => $column->name, $statement->from->outputs) // => ['x', 'y', 'n']
 */
final class RowsFromRelation extends TableUse
{
    /**
     * @param list<OutputColumn> $outputs Function columns in invocation order, then the ordinality column
     * @param list<string> $columnAliases Replacement names for the leading output columns
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(
        string $id,
        string $scopeId,
        TableDefinition $declaration,
        ?string $alias,
        Node $source,
        public readonly RowsFromTable $table,
        public readonly array $outputs,
        public readonly array $columnAliases = [],
    ) {
        Collections::objects($outputs, OutputColumn::class);
        Collections::strings($columnAliases);
        if (count($columnAliases) > count($outputs)) {
            throw new InvalidStructure('A function table has fewer columns than column aliases.');
        }
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
    }

    /**
     * Returns the ordered value expressions exposed by this relation.
     * @return list<Expression>
     */
    #[Override]
    public function resultExpressions(): array
    {
        return array_map(static fn (OutputColumn $output): Expression => $output->expression, $this->outputs);
    }

    /**
     * Returns a new relation occurrence in the supplied scope, retaining its source and operands.
     * @throws InvalidStructure
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new self($this->id, $scopeId, $this->declaration, $this->alias, $this->source, $this->table, $this->outputs, $this->columnAliases);
    }
}
