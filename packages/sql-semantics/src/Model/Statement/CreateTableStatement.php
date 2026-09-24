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
     * PostgreSQL alone copies template tables with LIKE and declares EXCLUDE constraints; each template stands at or before the last declared column position, in written order.
     *
     * @param list<\SqlSemantics\Model\Definition\IndexDeclaration> $indexes
     * @param list<\SqlSemantics\Model\Definition\Table\TemplatePlacement> $templates
     * @param list<\SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint> $exclusions
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Definition\TableDeclaration $definition,
        public readonly array $indexes = [],
        public readonly bool $ifNotExists = false,
        public readonly array $templates = [],
        public readonly array $exclusions = [],
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($exclusions, \SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint::class);
        if ($exclusions !== [] && $origin->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('EXCLUDE constraints are PostgreSQL table elements.');
        }
        \SqlSemantics\Model\Validation\Collections::objects($templates, \SqlSemantics\Model\Definition\Table\TemplatePlacement::class);
        $position = 0;
        foreach ($templates as $template) {
            if ($origin->dialect !== \SqlSemantics\Dialect::PostgreSql || $template->position < $position || $template->position > count($definition->table->columns)) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('LIKE templates are PostgreSQL table elements placed in order among the declared columns.');
            }
            $position = $template->position;
        }
        if ($origin->dialect !== \SqlSemantics\Dialect::PostgreSql && $definition->table->columns === []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A table declaration requires at least one column in this SQL dialect.');
        }
        foreach ($definition->table->columns as $column) {
            if ($column->type->dialect !== $origin->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Declared columns must use the statement dialect.');
            }
        }
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
        return new static($origin, $this->definition, $this->indexes, $this->ifNotExists, $this->templates, $this->exclusions);
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
        return $this->changed(new self($this->origin, $definition, $this->indexes, $this->ifNotExists, $this->templates, $this->exclusions));
    }

    /**
     * Replaces the LIKE templates copied into the declaration.
     *
     * @param list<\SqlSemantics\Model\Definition\Table\TemplatePlacement> $templates
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withTemplates(array $templates): self
    {
        return $this->changed(new self($this->origin, $this->definition, $this->indexes, $this->ifNotExists, $templates, $this->exclusions));
    }

    /**
     * Replaces the EXCLUDE constraints of the declaration.
     *
     * @param list<\SqlSemantics\Model\Definition\Relation\Constraint\ExclusionConstraint> $exclusions
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withExclusions(array $exclusions): self
    {
        return $this->changed(new self($this->origin, $this->definition, $this->indexes, $this->ifNotExists, $this->templates, $exclusions));
    }

}
