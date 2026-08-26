<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MultiTruncateMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\SynchronizeMutation;
use ZtdQuery\Shadow\Mutation\TruncateMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTableState;

/**
 * Resolves the appropriate ShadowMutation for a given PostgreSQL SQL statement.
 */
final class PgSqlMutationResolver
{
    private ShadowStore $shadowStore;
    private TableDefinitionRegistry $registry;
    private SchemaParser $schemaParser;
    private PgSqlParser $parser;
    private PgSqlPartitionParser $partitionParser;

    /**
     * Binds the instance to what it will work from.
     *
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     * @param SchemaParser $schemaParser
     * @param PgSqlParser $parser
     */
    public function __construct(
        ShadowStore $shadowStore,
        TableDefinitionRegistry $registry,
        SchemaParser $schemaParser,
        PgSqlParser $parser
    ) {
        $this->shadowStore = $shadowStore;
        $this->registry = $registry;
        $this->schemaParser = $schemaParser;
        $this->parser = $parser;
        $this->partitionParser = new PgSqlPartitionParser();
    }

    /**
     * Resolve mutation for a given SQL statement.
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolve(string $sql, string $statementType, QueryKind $kind): ?ShadowMutation
    {
        return match ($statementType) {
            'INSERT' => $this->resolveInsert($sql),
            'UPDATE' => $this->resolveUpdate($sql),
            'DELETE' => $this->resolveDelete($sql),
            'MERGE' => $this->resolveMerge($sql),
            'TRUNCATE' => $this->resolveTruncate($sql),
            'CREATE_TABLE' => $this->resolveCreateTable($sql),
            'DROP_TABLE' => $this->resolveDropTable($sql),
            'ALTER_TABLE' => $this->resolveAlterTable($sql),
            default => null,
        };
    }

    /**
     * Answers what an INSERT would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function resolveInsert(string $sql): ShadowMutation
    {
        $tableName = $this->parser->extractInsertTable($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }

        $definition = $this->registry->get($tableName);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
        $storageTable = $this->storageTable($tableName);

        if ($this->parser->hasOnConflict($sql)) {
            $conflictInfo = $this->parser->extractOnConflictUpdateColumns($sql);
            $updateColumns = $conflictInfo['columns'];
            $candidateKeys = $definition?->candidateKeys();
            $conflictPredicate = null;
            $target = $this->parser->extractOnConflictTarget($sql);
            if ($target !== null && $definition !== null) {
                $resolved = $target->resolve(
                    $definition->candidateKeys(),
                    $definition->partialUniqueIndexes,
                    $sql,
                );
                $candidateKeys = $resolved['keys'];
                $conflictPredicate = $resolved['predicate'];
            }
            $expressionParser = new PgSqlUpsertExpressionParser();

            if ($updateColumns !== []) {
                $databaseEvaluated = $candidateKeys !== null && $candidateKeys->keys() !== [];
                /** @var array<string, \ZtdQuery\Shadow\Mutation\UpsertExpression|null> $resolvedValues */
                $resolvedValues = [];
                foreach ($conflictInfo['values'] as $col => $value) {
                    $resolvedValues[$col] = $databaseEvaluated
                        ? $expressionParser->parseIfSupported($value, $tableName)
                        : $expressionParser->parse($value, $tableName);
                }
                $predicate = $this->parser->extractOnConflictUpdateWhere($sql);

                return new UpsertMutation(
                    $storageTable,
                    $primaryKeys,
                    $updateColumns,
                    $resolvedValues,
                    $candidateKeys,
                    $predicate !== null
                        ? ($databaseEvaluated
                            ? $expressionParser->parseIfSupported($predicate, $tableName)
                            : $expressionParser->parse($predicate, $tableName))
                        : null,
                    databaseEvaluated: $databaseEvaluated,
                    updateSqlValues: $conflictInfo['values'],
                    updateSqlPredicate: $predicate,
                    conflictPredicate: $conflictPredicate !== null
                        ? $expressionParser->parse($conflictPredicate, $tableName)
                        : null,
                );
            }

            return new InsertMutation(
                $storageTable,
                $primaryKeys,
                true,
                candidateKeys: $candidateKeys,
                conflictPredicate: $conflictPredicate !== null
                    ? $expressionParser->parse($conflictPredicate, $tableName)
                    : null,
            );
        }

        return new InsertMutation(
            $storageTable,
            $primaryKeys,
            false,
            candidateKeys: $definition?->candidateKeys(),
        );
    }

    /**
     * @throws UnknownSchemaException
     */
    /**
     * Answers what an UPDATE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveUpdate(string $sql): ShadowMutation
    {
        $targetTable = $this->parser->extractUpdateTable($sql);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }

        $definition = $this->registry->get($targetTable);
        if ($definition === null && $this->shadowStore->state($targetTable) !== ShadowTableState::Initialized) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $storageTable = $this->storageTable($targetTable);
        $this->shadowStore->ensure($storageTable);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        return new UpdateMutation($storageTable, $primaryKeys);
    }

    /**
     * @throws UnknownSchemaException
     */
    /**
     * Answers what a DELETE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveDelete(string $sql): ShadowMutation
    {
        $targetTable = $this->parser->extractDeleteTable($sql);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve DELETE target');
        }

        $definition = $this->registry->get($targetTable);
        if ($definition === null && $this->shadowStore->state($targetTable) !== ShadowTableState::Initialized) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $storageTable = $this->storageTable($targetTable);
        $this->shadowStore->ensure($storageTable);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        return new DeleteMutation($storageTable, $primaryKeys);
    }

    /**
     * Answers what a MERGE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnknownSchemaException
     */
    public function resolveMerge(string $sql): ShadowMutation
    {
        $statement = (new PgSqlMergeParser())->parse($sql);
        $definition = $this->registry->get($statement->targetTable);
        if ($definition === null
            && $this->shadowStore->state($statement->targetTable) !== ShadowTableState::Initialized
        ) {
            throw new UnknownSchemaException($sql, $statement->targetTable, 'table');
        }

        return new SynchronizeMutation(
            $this->storageTable($statement->targetTable),
            $definition,
            $sql,
        );
    }

    /**
     * Answers what a TRUNCATE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function resolveTruncate(string $sql): ShadowMutation
    {
        $tableNames = $this->parser->extractTruncateTables($sql);
        if ($tableNames === []) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve TRUNCATE target');
        }
        if (count($tableNames) > 1) {
            return new MultiTruncateMutation($tableNames);
        }

        return new TruncateMutation($tableNames[0]);
    }

    /**
     * @throws UnknownSchemaException
     */
    /**
     * Answers what a CREATE TABLE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveCreateTable(string $sql): ShadowMutation
    {
        $tableName = $this->parser->extractCreateTableName($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifNotExists = $this->parser->hasIfNotExists($sql);

        if (!$ifNotExists && $this->registry->has($tableName)) {
            throw new UnsupportedSqlException($sql, 'Table already exists');
        }

        $parentTable = $this->partitionParser->parentTable($sql);
        if ($parentTable !== null) {
            $parentDefinition = $this->registry->get($parentTable);
            if ($parentDefinition === null) {
                throw new UnknownSchemaException($sql, $parentTable, 'table');
            }
            if ($parentDefinition->partitionKey === null) {
                throw new UnsupportedSqlException($sql, 'Partition parent has no partition key metadata');
            }
            $relation = $this->partitionParser->parseRelation($sql, $parentDefinition->partitionKey);
            if ($relation === null) {
                throw new UnsupportedSqlException($sql, 'Unsupported partition bound');
            }

            $definition = $parentDefinition->withPartitionRelation($relation);
            $childKey = $this->partitionParser->parseKey($sql);
            if ($childKey !== null) {
                $definition = $definition->withPartitionKey($childKey);
            }

            return new CreateTableMutation($tableName, $definition, $this->registry, $sql, $ifNotExists);
        }

        if ($this->parser->hasCreateTableLike($sql)) {
            $sourceTable = $this->parser->extractCreateTableLikeSource($sql);
            if ($sourceTable === null || !$this->registry->has($sourceTable)) {
                throw new UnknownSchemaException($sql, $sourceTable ?? 'unknown', 'table');
            }

            return new CreateTableLikeMutation($tableName, $sourceTable, $this->registry, $ifNotExists);
        }

        if ($this->parser->hasCreateTableAsSelect($sql)) {
            $selectSql = $this->parser->extractCreateTableSelectSql($sql);
            $columnNames = $selectSql !== null ? $this->extractSelectColumnNames($selectSql) : [];

            return new CreateTableAsSelectMutation(
                $tableName,
                $columnNames,
                $this->registry,
                new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT'),
                $ifNotExists,
            );
        }

        $definition = $this->schemaParser->parse($sql);

        return new CreateTableMutation($tableName, $definition, $this->registry, $sql, $ifNotExists);
    }

    /**
     * Answers which table a partition's rows are actually held in.
     *
     * @param string $tableName Table it belongs to
     *
     * @return string What it answers
     */
    public function storageTable(string $tableName): string
    {
        $seen = [];
        while (!in_array($tableName, $seen, true)) {
            $seen[] = $tableName;
            $parent = $this->registry->get($tableName)?->partitionRelation?->parentTable;
            if ($parent === null) {
                return $tableName;
            }
            $tableName = $parent;
        }

        return $tableName;
    }

    /**
     * @throws UnknownSchemaException
     */
    /**
     * Answers what a DROP would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveDropTable(string $sql): ShadowMutation
    {
        $tableName = $this->parser->extractDropTableName($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifExists = $this->parser->hasDropTableIfExists($sql);

        if (!$ifExists && !$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return new DropTableMutation($tableName, $this->registry, $sql, $ifExists);
    }

    /**
     * @throws UnknownSchemaException
     */
    /**
     * Answers what an ALTER TABLE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation|null What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveAlterTable(string $sql): ?ShadowMutation
    {
        $tableName = $this->parser->extractAlterTableName($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        if (!$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        throw new UnsupportedSqlException($sql, 'ALTER TABLE not yet supported for PostgreSQL');
    }

    /**
     * Answers the names a SELECT would give the columns it answers.
     *
     * @param string $selectSql The select sql
     *
     * @return list<string> What it answers
     */
    public function extractSelectColumnNames(string $selectSql): array
    {
        $columns = [];

        if (preg_match('/SELECT\s+(.+?)\s+FROM\b/is', $selectSql, $m) !== 1) {
            if (preg_match('/SELECT\s+(.+)$/is', $selectSql, $m) !== 1) {
                return [];
            }
        }

        $selectList = $m[1];

        if (trim($selectList) === '*') {
            return [];
        }

        $items = $this->splitByTopLevelComma($selectList);
        foreach ($items as $item) {
            $item = trim($item);
            if (preg_match('/\bAS\s+"?([a-zA-Z_]\w*)"?\s*$/i', $item, $aliasMatch) === 1) {
                $columns[] = $aliasMatch[1];
            } elseif (preg_match('/^(?:(?:"[^"]+"|\w+)\.)?("([^"]+)"|([a-zA-Z_]\w*))\s*$/', $item, $colMatch) === 1) {
                $quotedColumn = $colMatch[2] ?? '';
                $columns[] = $quotedColumn !== '' ? $quotedColumn : ($colMatch[3] ?? '');
            } else {
                $replaced = preg_replace('/[^a-zA-Z0-9_]/', '_', $item);
                $columns[] = is_string($replaced) ? $replaced : 'col';
            }
        }

        return $columns;
    }

    /**
     * Splits text where the statement itself separates it, not where something inside it does.
     *
     * @param string $str The str
     *
     * @return list<string> What it answers
     */
    public function splitByTopLevelComma(string $str): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $char = $str[$i];

            if ($inSingleQuote) {
                $current .= $char;
                if ($char === "'" && isset($str[$i + 1]) && $str[$i + 1] === "'") {
                    $current .= "'";
                    $i++;
                } elseif ($char === "'") {
                    $inSingleQuote = false;
                }
                continue;
            }

            if ($inDoubleQuote) {
                $current .= $char;
                if ($char === '"' && isset($str[$i + 1]) && $str[$i + 1] === '"') {
                    $current .= '"';
                    $i++;
                } elseif ($char === '"') {
                    $inDoubleQuote = false;
                }
                continue;
            }

            if ($char === "'") {
                $current .= $char;
                $inSingleQuote = true;
                continue;
            }

            if ($char === '"') {
                $current .= $char;
                $inDoubleQuote = true;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $current .= $char;
                continue;
            }

            if ($char === ')') {
                $depth--;
                $current .= $char;
                continue;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $val = trim($current);
        if ($val !== '') {
            $parts[] = $val;
        }

        return $parts;
    }
}
