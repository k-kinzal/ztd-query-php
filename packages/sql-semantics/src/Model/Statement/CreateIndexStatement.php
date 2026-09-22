<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed CreateIndexStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
  * @example Inspecting CreateIndexStatement
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER DEFAULT 1)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('CREATE INDEX ix ON t(id)');
 *     $statement instanceof \SqlSemantics\Model\Statement\CreateIndexStatement // => true
 */
final class CreateIndexStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Definition\IndexDeclaration $index,
        public readonly \SqlSemantics\Model\Relation\TableReference|\SqlSemantics\Model\Relation\OnlyTableReference $table,
        public readonly bool $ifNotExists = false,
        public readonly bool $concurrently = false,
    ) {
        parent::__construct($origin);
        \SqlSemantics\Model\Validation\StatementOperands::relation($table, $origin->dialect);
        $definition = $index->definition;
        $expected = $table->name->parts;
        if ($table->alias !== null || $definition->table !== $expected) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An index must identify its unaliased owning table.');
        }
        if ($concurrently && $origin->dialect !== \SqlSemantics\Dialect::PostgreSql) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Concurrent index creation requires PostgreSQL.');
        }
        if ($definition->name === null && ($ifNotExists || $origin->dialect !== \SqlSemantics\Dialect::PostgreSql)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('This index creation requires an explicit index name.');
        }

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
        return new static($origin, $this->index, $this->table, $this->ifNotExists, $this->concurrently);
    }


    /**
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function withKey(int $ordinal, \SqlSemantics\Model\Expression $expression): self
    {
        $key = $this->index->definition->elements[$ordinal] ?? null;
        if ($key === null) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('The index key does not exist.');
        }
        $definition = $this->index->definition;
        $keys = $definition->elements;
        $keys[$ordinal] = new \SqlSemantics\Schema\Index\ExpressionKey($expression, $key->direction, $key->nulls, $key->collation, $key->operatorClass, $key->operatorParameters, $key->source);
        $index = new \SqlSemantics\Model\Definition\IndexDeclaration(new \SqlSemantics\Schema\IndexDefinition($definition->schema, $definition->name, $definition->table, array_values($keys), $definition->unique, $definition->method, $definition->include, $definition->predicate, $definition->source, $definition->properties));
        return $this->changed(new self($this->origin, $index, $this->table, $this->ifNotExists, $this->concurrently));
    }

}
