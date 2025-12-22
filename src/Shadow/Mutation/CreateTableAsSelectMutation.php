<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Rewrite\Shadowing\CteShadowing;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowStore;
use PhpMyAdmin\SqlParser\Statements\SelectStatement;

/**
 * Applies CREATE TABLE ... AS SELECT ... operation to the virtual schema.
 * This mutation creates a new table with structure and data from SELECT.
 */
final class CreateTableAsSelectMutation implements ShadowMutation
{
    /**
     * New table name to create.
     *
     * @var string
     */
    private string $tableName;

    /**
     * SELECT statement to derive schema and data from.
     *
     * @var SelectStatement
     */
    private SelectStatement $selectStatement;

    /**
     * Schema registry to register the table.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * Shadow store for data.
     *
     * @var ShadowStore
     */
    private ShadowStore $shadowStore;

    /**
     * CTE shadowing for query rewriting.
     *
     * @var CteShadowing
     */
    private CteShadowing $shadowing;

    /**
     * Whether to skip if table already exists (IF NOT EXISTS).
     *
     * @var bool
     */
    private bool $ifNotExists;

    /**
     * @param string $tableName The name of the new table to create.
     * @param SelectStatement $selectStatement The SELECT statement.
     * @param SchemaRegistry $schemaRegistry The schema registry.
     * @param ShadowStore $shadowStore The shadow store.
     * @param CteShadowing $shadowing The CTE shadowing helper.
     * @param bool $ifNotExists Whether to skip if table exists.
     */
    public function __construct(
        string $tableName,
        SelectStatement $selectStatement,
        SchemaRegistry $schemaRegistry,
        ShadowStore $shadowStore,
        CteShadowing $shadowing,
        bool $ifNotExists = false
    ) {
        $this->tableName = $tableName;
        $this->selectStatement = $selectStatement;
        $this->schemaRegistry = $schemaRegistry;
        $this->shadowStore = $shadowStore;
        $this->shadowing = $shadowing;
        $this->ifNotExists = $ifNotExists;
    }

    /**
     * {@inheritDoc}
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        // Check if target table already exists in virtual schema
        if ($this->schemaRegistry->hasVirtualTableDefinition($this->tableName)) {
            if ($this->ifNotExists) {
                return;
            }
            throw new \RuntimeException("Table '{$this->tableName}' already exists.");
        }

        // Build column definitions from SELECT expression list or from result rows
        $columns = $this->extractColumns();
        if ($columns === [] && $rows !== []) {
            // For SELECT *, get columns from the result set
            $columns = array_keys($rows[0]);
        }
        if ($columns === []) {
            throw new \RuntimeException("Cannot determine columns for CREATE TABLE AS SELECT.");
        }

        // Build a CREATE TABLE SQL with generic column types
        $columnDefs = [];
        foreach ($columns as $column) {
            // Use TEXT as a generic type since we can't determine exact types
            $columnDefs[] = "`{$column}` TEXT";
        }

        $createSql = "CREATE TABLE `{$this->tableName}` (" . implode(', ', $columnDefs) . ")";

        // Register the new table
        $this->schemaRegistry->register($this->tableName, $createSql);

        // Store the rows from the SELECT result
        $store->set($this->tableName, $rows);
    }

    /**
     * Extract column names from the SELECT expression list.
     *
     * @return list<string>
     */
    private function extractColumns(): array
    {
        /** @var list<string> $columns */
        $columns = [];

        if ($this->selectStatement->expr === []) {
            return $columns;
        }

        foreach ($this->selectStatement->expr as $expr) {
            // Use alias if available, otherwise use the expression
            if (property_exists($expr, 'alias') && is_string($expr->alias) && $expr->alias !== '') {
                $columns[] = $expr->alias;
            } elseif (property_exists($expr, 'column') && is_string($expr->column) && $expr->column !== '') {
                $columns[] = $expr->column;
            } elseif (property_exists($expr, 'expr') && is_string($expr->expr) && $expr->expr !== '' && $expr->expr !== '*') {
                // For expressions like "1+1", use the expression as column name
                // This might not be ideal, but it's a fallback
                $replaced = preg_replace('/[^a-zA-Z0-9_]/', '_', $expr->expr);
                $columns[] = is_string($replaced) ? $replaced : 'col';
            }
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }

    /**
     * Get the SELECT SQL for execution.
     *
     * @return string
     */
    public function getSelectSql(): string
    {
        $selectSql = $this->selectStatement->build();
        return $this->shadowing->apply($selectSql, $this->shadowStore->getAll());
    }
}
