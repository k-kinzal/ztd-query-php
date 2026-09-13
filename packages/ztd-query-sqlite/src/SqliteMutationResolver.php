<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Resolves the appropriate ShadowMutation for a given SQLite SQL statement.
 */
final class SqliteMutationResolver
{
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private SchemaParser $schemaParser;
    private SqliteParser $parser;

    /**
     * Binds the dependencies used by this operation.
     */
    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser,
        SqliteParser $parser
    ) {
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->schemaParser = $schemaParser;
        $this->parser = $parser;
    }

    /**
     * Resolve mutation for a given SQL statement.
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolve(string $sql, QueryKind $kind): ?ShadowMutation
    {
        $type = $this->parser->classifyStatement($sql);
        if ($type === null) {
            return null;
        }

        return match ($type) {
            'UPDATE' => (new Mutation\Resolution\RowMutationResolver($this->parser, $this->registry, $this->shadowStore))->resolveUpdate($sql),
            'DELETE' => (new Mutation\Resolution\RowMutationResolver($this->parser, $this->registry, $this->shadowStore))->resolveDelete($sql),
            'INSERT' => (new Mutation\Resolution\InsertMutationResolver($this->parser, $this->registry))->resolveInsert($sql),
            'CREATE_TABLE' => (new Mutation\Resolution\TableMutationResolver($this->parser, $this->registry, $this->schemaParser))->resolveCreateTable($sql),
            'DROP_TABLE' => (new Mutation\Resolution\TableMutationResolver($this->parser, $this->registry, $this->schemaParser))->resolveDropTable($sql),
            'ALTER_TABLE' => (new Mutation\Resolution\TableMutationResolver($this->parser, $this->registry, $this->schemaParser))->resolveAlterTable($sql),
            default => null,
        };
    }

}
