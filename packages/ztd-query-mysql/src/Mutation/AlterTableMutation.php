<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Applies an ALTER TABLE to what the shadow knows a table to be.
 *
 * ZTD holds a table as a definition, but ALTER is written against a
 * declaration. So the definition is written back out as CREATE TABLE, the
 * operations are applied to that, and the result is read as a definition
 * again -- which means one reader decides what a declaration means, whether
 * the statement that wrote it was CREATE or ALTER.
 */
final class AlterTableMutation implements ShadowMutation
{
    /** @var string The table the statement is against, as it is now called */
    private string $tableName;

    /**
     * @param string $tableName Table the statement is against
     * @param AlterStatement $alterStatement Statement to simulate
     * @param TableDefinitionRegistry $registry What each table declares
     * @param SchemaParser $schemaParser Reads a declaration back as a definition
     * @param MySqlTableRedeclaration $redeclaration Writes a definition back out as a declaration
     * @param AlterTableOperation|null $operations Applies one operation, or null to build one from the statement
     */
    public function __construct(
        string $tableName,
        private readonly AlterStatement $alterStatement,
        private readonly TableDefinitionRegistry $registry,
        private readonly SchemaParser $schemaParser,
        private readonly MySqlTableRedeclaration $redeclaration = new MySqlTableRedeclaration(),
        private readonly ?AlterTableOperation $operations = null,
    ) {
        $this->tableName = $tableName;
    }

    /**
     * {@inheritDoc}
     *
     * @throws SchemaNotFoundException When nothing has declared the table
     * @throws UnsupportedSqlException When the statement asks for something ZTD cannot simulate
     * @throws ColumnAlreadyExistsException When it adds a column the table already has
     * @throws ColumnNotFoundException When it changes a column the table does not have
     */
    public function apply(ShadowStore $store, array $rows): void
    {
        $definition = $this->registry->get($this->tableName);
        if ($definition === null) {
            throw new SchemaNotFoundException($this->alterStatement->build(), $this->tableName);
        }

        $createStmt = $this->declarationOf($this->redeclaration->sqlFor($this->tableName, $definition));
        $operations = $this->operations ?? new AlterTableOperation($this->alterStatement, $this->registry);

        foreach ($this->alterStatement->altered ?? [] as $op) {
            $this->tableName = $operations->applyTo($createStmt, $op, $store, $definition, $this->tableName);
        }

        $altered = $this->schemaParser->parse($createStmt->build());
        if ($altered === null) {
            throw new UnsupportedSqlException($this->alterStatement->build(), 'ALTER TABLE');
        }
        if ($definition->partitioning !== null) {
            $altered = $altered->withPartitioning($definition->partitioning);
        }

        $this->registry->unregister($this->tableName);
        $this->registry->register($this->tableName, $altered);
    }

    /**
     * Reads a declaration ZTD wrote itself.
     *
     * @param string $sql Declaration to read
     *
     * @return CreateStatement The declaration, as the parser reads it
     *
     * @throws UnsupportedSqlException When ZTD cannot read back what it wrote
     */
    public function declarationOf(string $sql): CreateStatement
    {
        $statement = (new Parser($sql))->statements[0] ?? null;
        if (!$statement instanceof CreateStatement) {
            throw new UnsupportedSqlException($this->alterStatement->build(), 'ALTER TABLE');
        }

        return $statement;
    }

    /**
     * {@inheritDoc}
     */
    public function tableName(): string
    {
        return $this->tableName;
    }
}
