<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed CreateTableStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
  * @example Inspecting CreateTableStatement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('create temporary table t(id integer default 1, constraint pk primary key(id))');
 *     $statement instanceof \SqlSemantics\Model\Statement\CreateTableStatement // => true
 */
final class CreateTableStatement extends \SqlSemantics\Model\BoundStatement
{
    /**
     * @param list<\SqlSemantics\Model\Definition\IndexDeclaration> $indexes
     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Definition\TableDeclaration $definition,
        public readonly array $indexes = [],
        public readonly bool $ifNotExists = false,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\Collections::objects($indexes, \SqlSemantics\Model\Definition\IndexDeclaration::class);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->definition, $this->indexes, $this->ifNotExists);
    }


    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withColumn(\SqlSemantics\Schema\ColumnDefinition $target, \SqlSemantics\Schema\ColumnDefinition $replacement): self
    {
        if (!in_array($target, $this->definition->table->columns, true)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The column does not belong to this table declaration.');
        }
        $table = $this->definition->table;
        $columns = array_map(static fn (\SqlSemantics\Schema\ColumnDefinition $column): \SqlSemantics\Schema\ColumnDefinition => $column === $target ? $replacement : $column, $table->columns);
        $definition = new \SqlSemantics\Model\Definition\TableDeclaration(new \SqlSemantics\Schema\TableDefinition($table->schema, $table->name, $columns, $table->constraints, $table->source, $table->resolved, $table->indexes, $table->properties));
        return $this->changed(new self($this->origin, $definition, $this->indexes, $this->ifNotExists));
    }

}
