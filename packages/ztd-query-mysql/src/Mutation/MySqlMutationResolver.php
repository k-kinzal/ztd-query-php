<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use PhpMyAdmin\SqlParser\Statements\DropStatement;
use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use PhpMyAdmin\SqlParser\Statements\TruncateStatement;
use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Transformer\DeleteTransformer;
use ZtdQuery\Platform\MySql\Transformer\UpdateTransformer;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Resolves the appropriate ShadowMutation for a given SQL statement.
 *
 * This class depends on domain state (ShadowStore, TableDefinitionRegistry) to determine
 * primary keys, column metadata, and table existence needed for mutation construction.
 */
final class MySqlMutationResolver
{
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private SchemaParser $schemaParser;
    private UpdateTransformer $updateTransformer;
    private DeleteTransformer $deleteTransformer;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser,
        UpdateTransformer $updateTransformer,
        DeleteTransformer $deleteTransformer
    ) {
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->schemaParser = $schemaParser;
        $this->updateTransformer = $updateTransformer;
        $this->deleteTransformer = $deleteTransformer;
    }

    /**
     * Resolve mutation for a given statement.
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolve(string $sql, Statement $statement, QueryKind $kind): ?ShadowMutation
    {
        if ($statement instanceof UpdateStatement) {
            return (new Mutation\Statement\RowMutation($this->deleteTransformer, $this->registry, $this->shadowStore, $this->updateTransformer))->resolveUpdate($statement, $sql);
        }

        if ($statement instanceof DeleteStatement) {
            return (new Mutation\Statement\RowMutation($this->deleteTransformer, $this->registry, $this->shadowStore, $this->updateTransformer))->resolveDelete($statement, $sql);
        }

        if ($statement instanceof InsertStatement) {
            return (new Mutation\Statement\RowMutation($this->deleteTransformer, $this->registry, $this->shadowStore, $this->updateTransformer))->resolveInsert($statement, $sql);
        }

        if ($statement instanceof TruncateStatement) {
            return (new Mutation\Statement\RowMutation($this->deleteTransformer, $this->registry, $this->shadowStore, $this->updateTransformer))->resolveTruncate($statement, $sql);
        }

        if ($statement instanceof ReplaceStatement) {
            return (new Mutation\Statement\RowMutation($this->deleteTransformer, $this->registry, $this->shadowStore, $this->updateTransformer))->resolveReplace($statement, $sql);
        }

        if ($kind === QueryKind::DDL_SIMULATED) {
            if ($statement instanceof CreateStatement) {
                return (new Mutation\Statement\SchemaMutation($this->registry, $this->schemaParser))->resolveCreateTable($statement, $sql);
            }
            if ($statement instanceof DropStatement) {
                return (new Mutation\Statement\SchemaMutation($this->registry, $this->schemaParser))->resolveDropTable($statement, $sql);
            }
            if ($statement instanceof AlterStatement) {
                return (new Mutation\Statement\SchemaMutation($this->registry, $this->schemaParser))->resolveAlterTable($statement, $sql);
            }
        }

        return null;
    }

}
