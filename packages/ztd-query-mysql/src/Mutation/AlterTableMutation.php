<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use RuntimeException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies ALTER TABLE operation to the virtual schema.
 * This mutation modifies the table definition in the TableDefinitionRegistry.
 */
final class AlterTableMutation implements ShadowMutation
{
    private string $tableName;
    private AlterStatement $alterStatement;
    private TableDefinitionRegistry $registry;
    private SchemaParser $schemaParser;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(
        string $tableName,
        AlterStatement $alterStatement,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser
    ) {
        $this->tableName = $tableName;
        $this->alterStatement = $alterStatement;
        $this->registry = $registry;
        $this->schemaParser = $schemaParser;
    }

    /**
     * {@inheritDoc}
     * @throws SchemaNotFoundException
     * @throws RuntimeException
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $definition = $this->registry->get($this->tableName);
        if ($definition === null) {
            throw new SchemaNotFoundException($this->alterStatement->build(), $this->tableName);
        }

        $createSql = (new Alter\CreateTableRenderer($this->tableName))->buildCreateTableSql($definition);

        $parser = new \PhpMyAdmin\SqlParser\Parser($createSql);
        if ($parser->statements === []) {
            throw new RuntimeException("Failed to parse reconstructed schema for '{$this->tableName}'.");
        }

        $createStmt = $parser->statements[0];
        if (!$createStmt instanceof CreateStatement) {
            throw new RuntimeException("Reconstructed schema for '{$this->tableName}' is not a CREATE TABLE statement.");
        }

        foreach ($this->alterStatement->altered ?? [] as $op) {
            $this->tableName = (new Alter\OperationApplier($this->alterStatement, $this->registry, $this->tableName))->applyOperation($createStmt, $op, $store, $definition);
        }

        $newSql = $createStmt->build();
        $newDefinition = $this->schemaParser->parse($newSql);
        if ($newDefinition === null) {
            throw new RuntimeException("Failed to parse altered schema for '{$this->tableName}'.");
        }
        if ($definition->partitioning !== null) {
            $newDefinition = $newDefinition->withPartitioning($definition->partitioning);
        }

        $this->registry->unregister($this->tableName);
        $this->registry->register($this->tableName, $newDefinition);
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
