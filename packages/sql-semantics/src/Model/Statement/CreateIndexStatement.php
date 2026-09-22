<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement;

use Override;

/**
 * Typed CreateIndexStatement operands; unrelated statement fields cannot be supplied.
 * @visibility public
 */
final class CreateIndexStatement extends \SqlSemantics\Model\BoundStatement
{
    /**

     * @visibility SqlSemantics
     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Definition\IndexDeclaration $index,
        public readonly \SqlSemantics\Model\TableUse $table,
        public readonly bool $ifNotExists = false,
        public readonly bool $concurrently = false,
        public readonly bool $only = false,
    ) {
        parent::__construct($origin);
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
        return new static($origin, $this->index, $this->table, $this->ifNotExists, $this->concurrently, $this->only);
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
        return $this->changed(new self($this->origin, $index, $this->table, $this->ifNotExists, $this->concurrently, $this->only));
    }

}
