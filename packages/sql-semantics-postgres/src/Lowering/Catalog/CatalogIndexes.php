<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog;

use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclaration;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexDeclarationKind;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNamespace;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartition;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\TablePartitionRole;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\CatalogRelationCoordinates;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexCreationVisibility;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;

/**
 * Builds an immutable index namespace from verified catalog declarations.
 *
 * @visibility SqlSemantics
 */
final class CatalogIndexes
{
    /** @var list<Name> */
    private array $schemas = [];

    /** @var list<Name> */
    private array $opaque = [];

    /** @var list<IndexDeclaration> */
    private array $indexes = [];

    /** @var array<string, TablePartition> */
    private array $partitioned = [];

    /** @var array<string, true> */
    private array $uncertainTables = [];

    /**
     * Collects declarations from the bound input and updates the next catalog snapshot.
     */
    public function add(BoundStatement $binding): void
    {
        $statement = $binding->statement;
        $schema = $statement instanceof CreateSchema ? ($statement->name ?? $statement->authorization?->name) : null;
        if ($statement instanceof CreateSchema) {
            Check::input($schema !== null, 'Catalog schemas require a definite name.');
            $this->schemas[] = $schema;
        }
        $coordinates = new CatalogRelationCoordinates();
        foreach ($binding->facts->declarations as $relation) {
            $context = $binding->context;
            $found = $relation->name->schema ?? $context->declarationSchema;
            if ($relation->name->schema === null && !$coordinates->definite($context, $found)) {
                $this->uncertainTables[$coordinates->key($found, $relation->name->name)] = true;
                $this->opaque[] = $found;
            }
            $this->schemas[] = $found;
        }
        $items = $statement instanceof CreateSchema ? $statement->elements : [$statement];
        foreach ($items as $item) {
            if (!$item instanceof CreateTable) {
                continue;
            }
            [$namespace, $name] = $coordinates->owner($binding, $item, $schema);
            $partition = match (true) {
                $item->partitioning !== null => TablePartitionRole::Branch,
                $item->definition instanceof PartitionOf => TablePartitionRole::Leaf,
                default => null,
            };
            if ($partition !== null) {
                $this->partitioned[$coordinates->key($namespace, $name)] = new TablePartition($namespace, $name, $partition);
            }
            [$constraint, $unknown, $kind] = (new TableIndexFacts())->collect($item);
            if ($constraint !== null && $kind !== null) {
                $owner = $coordinates->key($namespace, $name);
                $kind = isset($this->partitioned[$owner]) ? IndexDeclarationKind::Partitioned : $kind;
                $this->indexes[] = new IndexDeclaration($namespace, $constraint, $name, $kind);
            }
            if ($unknown) {
                $this->opaque[] = $namespace;
            }
        }
    }

    /**
     * Collects the explicit named or anonymous index definitions supplied by one catalog statement.
     */
    public function addIndexes(BoundStatement $binding): void
    {
        $statement = $binding->statement;
        $enclosing = $statement instanceof CreateSchema ? ($statement->name ?? $statement->authorization?->name) : null;
        $coordinates = new CatalogRelationCoordinates();
        $visibility = new IndexCreationVisibility();
        $context = $binding->context;
        foreach ($statement instanceof CreateSchema ? $statement->elements : [$statement] as $index) {
            if (!$index instanceof CreateIndex) {
                continue;
            }
            [$schema, $table] = $coordinates->owner($binding, $index, $enclosing);
            $key = $coordinates->key($schema, $table);
            $written = $index->table->name;
            Check::input($written->catalog === null && !isset($this->uncertainTables[$key]), 'The indexed relation must have a definite local identity.');
            if ($written->schema === null && $enclosing === null) {
                $visibility->verifyPath($context, $this->indexes, $table, $schema);
            }
            $name = $index->name;
            if ($index->ifNotExists && $name !== null && $visibility->occupied($context, $this->indexes, $name, $schema)) {
                continue;
            }
            if ($name === null || ($index->ifNotExists && (!$context->complete || $this->snapshot()->hasUnknownNames($schema)))) {
                $this->opaque[] = $schema;
                continue;
            }
            $kind = match ($this->partitioned[$key]->role ?? null) {
                TablePartitionRole::Branch => IndexDeclarationKind::PartitionBranch,
                TablePartitionRole::Leaf => IndexDeclarationKind::PartitionLeaf,
                null => IndexDeclarationKind::Ordinary,
            };
            $this->indexes[] = new IndexDeclaration($schema, $name, $table, $kind);
        }
    }

    /**
     * Captures detached values for the next binding.
     */
    public function snapshot(): IndexNamespace
    {
        return new IndexNamespace($this->schemas, $this->indexes, $this->opaque, array_values($this->partitioned));
    }

}
