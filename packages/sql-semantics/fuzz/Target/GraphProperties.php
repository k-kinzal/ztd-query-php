<?php

declare(strict_types=1);

namespace Fuzz\Target;

use RuntimeException;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use WeakMap;

/**
 * Checks semantic graph invariants independently of the grammar generator.
 */
final class GraphProperties
{
    /**
     * @var WeakMap<BoundStatement|Expression, bool>
     */
    private readonly WeakMap $visited;

    /**
     * Verifies all types in the selected SQL dialect.
     */
    public function __construct(public readonly Dialect $dialect)
    {
        $this->visited = new WeakMap();
    }

    /**
     * Checks each shared statement graph once, including its nested dependencies.
     *
     * @throws RuntimeException
     */
    public function statement(BoundStatement $statement): void
    {
        if (isset($this->visited[$statement])) {
            return;
        }
        $this->visited[$statement] = true;
        if ($statement->kind === '' || $statement->scopeId === '') {
            throw new RuntimeException('A statement must have an operation and a scope.');
        }
        foreach ($statement->declarations as $declaration) {
            $this->declaration($declaration);
        }
        $this->join($statement->from);
        foreach ($statement->outputs as $ordinal => $output) {
            if ($output->ordinal !== $ordinal) {
                throw new RuntimeException('Output ordinals do not preserve projection order.');
            }
            $this->expression($output->expression);
        }
        foreach ([$statement->where, $statement->having, $statement->limit, $statement->offset] as $expression) {
            if ($expression !== null) {
                $this->expression($expression);
            }
        }
        foreach ([...$statement->groupBy, ...array_values($statement->assignments), ...array_merge([], ...$statement->rows), ...array_merge([], ...array_values($statement->clauses))] as $expression) {
            $this->expression($expression);
        }
        foreach ($statement->orderBy as $ordering) {
            $this->expression($ordering->expression);
        }
        foreach ([...array_values($statement->ctes), ...$statement->branches, ...$statement->queries] as $query) {
            $this->statement($query);
        }
        foreach ([...$statement->relations, ...$statement->targets] as $relation) {
            $this->declaration($relation->declaration);
            if ($relation->scopeId !== $statement->scopeId) {
                throw new RuntimeException('A relation belongs to the wrong scope.');
            }
            if ($relation->query !== null) {
                $this->statement($relation->query);
            }
        }
    }

    /**
     * Checks the structured schema facts that fixture generation consumes.
     *
     * @throws RuntimeException
     */
    public function declaration(\SqlSemantics\Schema\TableDefinition $table): void
    {
        foreach ($table->columns as $column) {
            if (!$this->validType($column->type)) {
                throw new RuntimeException('A declaration has an invalid column type descriptor.');
            }
        }
    }

    /**
     * SQLite's absent declared type is represented by an empty name with BLOB affinity.
     */
    public function validType(\SqlSemantics\Type\TypeDescriptor $type): bool
    {
        return $type->dialect === $this->dialect && ($type->name !== '' || ($this->dialect === Dialect::Sqlite && $type->affinity === 'blob'));
    }

    /**
     * Visits match predicates independently of projection lineage.
     */
    public function join(\SqlSemantics\Model\Join|\SqlSemantics\Model\TableUse|null $relation): void
    {
        if ($relation instanceof \SqlSemantics\Model\Join) {
            if ($relation->condition !== null) {
                $this->expression($relation->condition);
            }
            $this->join($relation->left);
            $this->join($relation->right);
        }
    }

    /**
     * Checks each shared expression graph once, including its nested dependencies.
     *
     * @throws RuntimeException
     */
    public function expression(Expression $expression): void
    {
        if (isset($this->visited[$expression])) {
            return;
        }
        $this->visited[$expression] = true;
        if (!$this->validType($expression->type)) {
            throw new RuntimeException('An expression has an invalid type descriptor.');
        }
        if ($expression->kind === ExpressionKind::Column && $expression->binding === null) {
            throw new RuntimeException('A resolved column is missing its declaration.');
        }
        if ($expression->kind === ExpressionKind::UnresolvedColumn && ($expression->reference === [] || $expression->binding !== null || $expression->type->name !== 'unknown')) {
            throw new RuntimeException('An unresolved reference was lost or assigned a fabricated type.');
        }
        if ($expression->binding !== null && !in_array($expression->binding->column, $expression->binding->table->columns, true)) {
            throw new RuntimeException('A column binding points outside its declaration.');
        }
        foreach ($expression->operands as $operand) {
            $this->expression($operand);
        }
        if ($expression->query !== null) {
            $this->statement($expression->query);
        }
    }
}
