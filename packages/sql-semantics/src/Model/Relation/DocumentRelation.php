<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;
use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\TableFunction;
use SqlSemantics\Model\TableUse;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Schema\TableDefinition;

/**
 * A relation produced by a declared JSON_TABLE or XMLTABLE row expansion.
 * @visibility public
 */
final class DocumentRelation extends TableUse
{
    /**
     * @var list<OutputColumn> Outputs derived from the document table's own declarations
     */
    public readonly array $outputs;

    /**
     * @param list<string> $columnAliases
     */
    public function __construct(string $id, string $scopeId, ?string $alias, Node $source, public readonly TableFunction\Json\JsonTable|TableFunction\Xml\XmlTable $table, public readonly array $columnAliases = [])
    {
        Collections::strings($columnAliases);
        $this->outputs = TableFunction\OutputColumns::derive($table, $source, $table->dialect);
        if (count($columnAliases) > count($this->outputs)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A document relation cannot alias more columns than it produces.');
        }
        $columns = [];
        foreach ($this->outputs as $index => $output) {
            $columns[] = new \SqlSemantics\Schema\ColumnDefinition($columnAliases[$index] ?? $output->name ?? '?column?', $output->expression->type, $output->expression->nullability, $source);
        }
        $declaration = new TableDefinition('', $alias ?? ($table instanceof TableFunction\Json\JsonTable ? 'json_table' : 'xmltable'), $columns, [], $source);
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function resultExpressions(): array
    {
        return array_map(static fn (OutputColumn $output): Expression => $output->expression, $this->outputs);
    }

    /**
     * Retains the complete document operation when moving the relation's namespace.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new static($this->id, $scopeId, $this->alias, $this->source, $this->table, $this->columnAliases);
    }
}
