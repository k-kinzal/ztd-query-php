<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite;

use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\SchemaParser;
use ZtdQuery\Platform\Sqlite\Mutation\AlterTableMutation;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\DropTableMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTableState;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

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
     * Binds the instance to what it will work from.
     *
     * @param ShadowStore $shadowStore
     * @param TableDefinitionRegistry $registry
     * @param SchemaParser $schemaParser
     * @param SqliteParser $parser
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
            'UPDATE' => $this->resolveUpdate($sql),
            'DELETE' => $this->resolveDelete($sql),
            'INSERT' => $this->resolveInsert($sql),
            'CREATE_TABLE' => $this->resolveCreateTable($sql),
            'DROP_TABLE' => $this->resolveDropTable($sql),
            'ALTER_TABLE' => $this->resolveAlterTable($sql),
            default => null,
        };
    }

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
        $targetTable = $this->parser->extractTargetTable($sql);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve UPDATE target');
        }
        $this->assertTableWasNotRemoved($sql, $targetTable);

        $definition = $this->registry->get($targetTable);
        if ($definition === null && $this->shadowStore->state($targetTable) !== ShadowTableState::Initialized) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $this->shadowStore->ensure($targetTable);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        return new UpdateMutation($targetTable, $primaryKeys);
    }

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
        $targetTable = $this->parser->extractTargetTable($sql);
        if ($targetTable === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve DELETE target');
        }
        $this->assertTableWasNotRemoved($sql, $targetTable);

        $trimmed = trim($this->parser->stripComments($sql));
        $upperTrimmed = strtoupper($trimmed);

        if (preg_match('/^DELETE\s+FROM\s+(?:"(?:[^"]|"")*"|`(?:[^`]|``)*`|\[(?:[^\]])*\]|[^\s;]+)\s*;?\s*$/i', $trimmed) === 1) {
        }

        $definition = $this->registry->get($targetTable);
        if ($definition === null && $this->shadowStore->state($targetTable) !== ShadowTableState::Initialized) {
            throw new UnknownSchemaException($sql, $targetTable, 'table');
        }

        $this->shadowStore->ensure($targetTable);
        $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

        return new DeleteMutation($targetTable, $primaryKeys);
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
        $tableName = $this->parser->extractTargetTable($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve INSERT target');
        }
        $this->assertTableWasNotRemoved($sql, $tableName);

        if ($this->parser->isReplace($sql)) {
            $definition = $this->registry->get($tableName);
            $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

            return new ReplaceMutation($tableName, $primaryKeys, $definition?->candidateKeys());
        }

        if ($this->parser->hasOnConflict($sql)) {
            $updateColumns = [];
            $definition = $this->registry->get($tableName);
            $databaseEvaluated = $definition !== null && $definition->candidateKeys()->keys() !== [];
            /** @var array<string, \ZtdQuery\Shadow\Mutation\UpsertExpression|null> $updateValues */
            $updateValues = [];
            $expressionParser = new SqliteUpsertExpressionParser();
            $onConflictUpdates = $this->parser->extractOnConflictUpdates($sql);
            foreach ($onConflictUpdates as $colName => $value) {
                $updateColumns[] = $colName;
                $updateValues[$colName] = $databaseEvaluated
                    ? $expressionParser->parseIfSupported($value, $tableName)
                    : $expressionParser->parse($value, $tableName);
            }

            if ($updateColumns !== []) {
                $primaryKeys = $definition !== null ? $definition->primaryKeys : [];
                $predicate = $this->parser->extractOnConflictUpdateWhere($sql);

                return new UpsertMutation(
                    $tableName,
                    $primaryKeys,
                    $updateColumns,
                    $updateValues,
                    $definition?->candidateKeys(),
                    $predicate !== null
                        ? ($databaseEvaluated
                            ? $expressionParser->parseIfSupported($predicate, $tableName)
                            : $expressionParser->parse($predicate, $tableName))
                        : null,
                    databaseEvaluated: $databaseEvaluated,
                    updateSqlValues: $onConflictUpdates,
                    updateSqlPredicate: $predicate,
                );
            }

            $definition = $this->registry->get($tableName);
            $primaryKeys = $definition !== null ? $definition->primaryKeys : [];

            return new InsertMutation(
                $tableName,
                $primaryKeys,
                true,
                candidateKeys: $definition?->candidateKeys(),
            );
        }

        $isIgnore = $this->parser->isInsertIgnore($sql);

        $definition = $this->registry->get($tableName);
        $primaryKeys = $isIgnore ? ($definition !== null ? $definition->primaryKeys : []) : [];

        return new InsertMutation(
            $tableName,
            $primaryKeys,
            $isIgnore,
            candidateKeys: $definition?->candidateKeys(),
        );
    }

    /**
     * Answers what a CREATE TABLE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function resolveCreateTable(string $sql): ShadowMutation
    {
        $tableName = $this->parser->extractTargetTable($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifNotExists = (bool) preg_match('/\bIF\s+NOT\s+EXISTS\b/i', $sql);

        if (!$ifNotExists && $this->registry->has($tableName)) {
            throw new UnsupportedSqlException($sql, 'Table already exists');
        }

        $definition = $this->schemaParser->parse($sql);

        return new CreateTableMutation($tableName, $definition, $this->registry, $sql, $ifNotExists);
    }

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
        $tableName = $this->parser->extractTargetTable($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $ifExists = (bool) preg_match('/\bIF\s+EXISTS\b/i', $sql);

        if ($this->registry->isRemoved($tableName)) {
            if ($ifExists) {
                return new DropTableMutation($tableName, $this->registry, $sql, true);
            }
            throw new UnsupportedSqlException($sql, 'Table was removed from the virtual schema');
        }

        if (!$ifExists && !$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return new DropTableMutation($tableName, $this->registry, $sql, $ifExists);
    }

    /**
     * Answers what an ALTER TABLE would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function resolveAlterTable(string $sql): ShadowMutation
    {
        $tableName = $this->parser->extractTargetTable($sql);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $this->assertTableWasNotRemoved($sql, $tableName);

        if (!$this->registry->has($tableName)) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        $operation = $this->alterOperation($sql);
        if ($operation === null) {
            throw new UnsupportedSqlException($sql, 'Unsupported ALTER TABLE operation');
        }

        return match ($operation['kind']) {
            'add' => $this->resolveAlterAddColumn($sql, $tableName, $operation['clause']),
            'drop' => $this->resolveAlterDropColumn($sql, $tableName, $operation['clause']),
            'rename_table' => $this->resolveAlterRenameTable($sql, $tableName, $operation['clause']),
            'rename_column' => $this->resolveAlterRenameColumn($sql, $tableName, $operation['clause']),
        };
    }

    /**
     * Answers what adding a column would do to the shadow.
     *
     * SQLite alters a table by building the new one and copying into it, so a
     * column added is a table declared afresh with that column on the end.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     * @param string $columnSql The column sql
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws ColumnAlreadyExistsException
     */
    public function resolveAlterAddColumn(string $sql, string $tableName, string $columnSql): ShadowMutation
    {
        $existing = $this->definition($sql, $tableName);
        $added = $this->schemaParser->parse('CREATE TABLE "__ztd_alter" (' . $columnSql . ')');
        if ($added === null) {
            throw new UnsupportedSqlException($sql, 'Cannot parse ADD COLUMN');
        }
        if (count($added->columns) !== 1) {
            throw new UnsupportedSqlException($sql, 'Cannot parse ADD COLUMN');
        }

        $columnName = $added->columns[0];
        if ($this->existingColumn($existing, $columnName) !== null) {
            throw new ColumnAlreadyExistsException($sql, $tableName, $columnName);
        }

        $foreignKeys = $existing->foreignKeys;
        foreach ($added->foreignKeys as $name => $foreignKey) {
            while (isset($foreignKeys[$name])) {
                $name .= '_added';
            }
            $foreignKeys[$name] = $foreignKey;
        }

        $definition = new TableDefinition(
            [...$existing->columns, $columnName],
            array_merge($existing->columnTypes, $added->columnTypes),
            $existing->primaryKeys,
            [...$existing->notNullColumns, ...$added->notNullColumns],
            $existing->uniqueConstraints,
            array_merge($existing->typedColumns, $added->typedColumns),
            array_merge($existing->columnDefaults, $added->columnDefaults),
            $existing->identityStrategies,
            array_merge($existing->generatedExpressions, $added->generatedExpressions),
            $foreignKeys,
        );
        $projection = $this->quotedColumns($existing->columns);
        $projection[] = ($added->columnDefaults[$columnName] ?? 'NULL')
            . ' AS ' . $this->quote($columnName);

        return $this->alterMutation($sql, $tableName, $tableName, $definition, $projection);
    }

    /**
     * Answers what dropping a column would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     * @param string $columnClause The column clause
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws ColumnNotFoundException
     */
    public function resolveAlterDropColumn(string $sql, string $tableName, string $columnClause): ShadowMutation
    {
        $requestedName = $this->singleIdentifier($columnClause);
        if ($requestedName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot parse DROP COLUMN');
        }

        $existing = $this->definition($sql, $tableName);
        $columnName = $this->existingColumn($existing, $requestedName);
        if ($columnName === null) {
            throw new ColumnNotFoundException($sql, $tableName, $requestedName);
        }

        $newColumns = self::withoutColumn($existing->columns, $columnName);
        $newColumnTypes = self::withoutMapKey($existing->columnTypes, $columnName);
        $newTypedColumns = self::withoutMapKey($existing->typedColumns, $columnName);
        $newDefaults = self::withoutMapKey($existing->columnDefaults, $columnName);
        $newIdentityStrategies = self::withoutMapKey($existing->identityStrategies, $columnName);
        $newGeneratedExpressions = self::withoutMapKey($existing->generatedExpressions, $columnName);
        $newForeignKeys = array_filter(
            $existing->foreignKeys,
            static fn (ForeignKeyDefinition $foreignKey): bool => !in_array(
                $columnName,
                $foreignKey->columns,
                true,
            ),
        );
        $newNotNull = self::withoutColumn($existing->notNullColumns, $columnName);
        $newPrimaryKeys = self::withoutColumn($existing->primaryKeys, $columnName);
        $newUniqueConstraints = [];
        foreach ($existing->uniqueConstraints as $name => $columns) {
            $filtered = self::withoutColumn($columns, $columnName);
            if ($filtered !== []) {
                $newUniqueConstraints[$name] = $filtered;
            }
        }

        $definition = new TableDefinition(
            $newColumns,
            $newColumnTypes,
            $newPrimaryKeys,
            $newNotNull,
            $newUniqueConstraints,
            $newTypedColumns,
            $newDefaults,
            $newIdentityStrategies,
            $newGeneratedExpressions,
            $newForeignKeys,
        );

        return $this->alterMutation(
            $sql,
            $tableName,
            $tableName,
            $definition,
            $this->quotedColumns($newColumns),
        );
    }

    /**
     * Answers what renaming a table would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     * @param string $tableClause The table clause
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function resolveAlterRenameTable(string $sql, string $tableName, string $tableClause): ShadowMutation
    {
        $newName = $this->singleIdentifier($tableClause);
        if ($newName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot parse RENAME TO');
        }

        $existing = $this->definition($sql, $tableName);

        return $this->alterMutation(
            $sql,
            $tableName,
            $newName,
            $existing,
            $this->quotedColumns($existing->columns),
        );
    }

    /**
     * Answers what renaming a column would do to the shadow.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     * @param string $columnClause The column clause
     *
     * @return ShadowMutation What it answers
     *
     * @throws UnsupportedSqlException
     * @throws ColumnNotFoundException
     * @throws ColumnAlreadyExistsException
     */
    public function resolveAlterRenameColumn(string $sql, string $tableName, string $columnClause): ShadowMutation
    {
        $renamed = $this->renamedIdentifiers($columnClause);
        if ($renamed === null) {
            throw new UnsupportedSqlException($sql, 'Cannot parse RENAME COLUMN');
        }

        [$requestedName, $newName] = $renamed;
        $existing = $this->definition($sql, $tableName);
        $oldName = $this->existingColumn($existing, $requestedName);
        if ($oldName === null) {
            throw new ColumnNotFoundException($sql, $tableName, $requestedName);
        }
        $duplicate = $this->existingColumn($existing, $newName);
        if ($duplicate !== null && strcasecmp($duplicate, $oldName) !== 0) {
            throw new ColumnAlreadyExistsException($sql, $tableName, $newName);
        }

        $newColumns = self::renamedColumns($existing->columns, $oldName, $newName);
        $newUniqueConstraints = [];
        foreach ($existing->uniqueConstraints as $name => $columns) {
            $newUniqueConstraints[$name] = self::renamedColumns($columns, $oldName, $newName);
        }

        $definition = new TableDefinition(
            $newColumns,
            self::renamedMapKey($existing->columnTypes, $oldName, $newName),
            self::renamedColumns($existing->primaryKeys, $oldName, $newName),
            self::renamedColumns($existing->notNullColumns, $oldName, $newName),
            $newUniqueConstraints,
            self::renamedMapKey($existing->typedColumns, $oldName, $newName),
            self::renamedMapKey($existing->columnDefaults, $oldName, $newName),
            self::renamedMapKey($existing->identityStrategies, $oldName, $newName),
            self::renamedMapKey($existing->generatedExpressions, $oldName, $newName),
            array_map(
                static fn (ForeignKeyDefinition $foreignKey): ForeignKeyDefinition => self::renamedForeignKey(
                    $foreignKey,
                    $oldName,
                    $newName,
                ),
                $existing->foreignKeys,
            ),
        );
        $projection = [];
        foreach ($existing->columns as $column) {
            $expression = $this->quote($column);
            if ($column === $oldName) {
                $expression .= ' AS ' . $this->quote($newName);
            }
            $projection[] = $expression;
        }

        return $this->alterMutation($sql, $tableName, $tableName, $definition, $projection);
    }

    /**
     * Answers what a table holds, refusing one nothing has declared.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     *
     * @return TableDefinition What it answers
     *
     * @throws UnknownSchemaException
     */
    public function definition(string $sql, string $tableName): TableDefinition
    {
        $definition = $this->registry->get($tableName);
        if ($definition === null) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }

        return $definition;
    }

    /**
     * Refuses a statement against a table an earlier one dropped.
     *
     * @param string $sql Statement being read, as written
     * @param string $tableName Table it belongs to
     *
     * @throws UnsupportedSqlException
     */
    public function assertTableWasNotRemoved(string $sql, string $tableName): void
    {
        if ($this->registry->isRemoved($tableName)) {
            throw new UnsupportedSqlException($sql, 'Table was removed from the virtual schema');
        }
    }

    /**
     * Answers the mutation that replaces a table with an altered one.
     *
     * @param string $sql Statement being read, as written
     * @param string $sourceTable The source table
     * @param string $targetTable The target table
     * @param TableDefinition $definition What the table holds
     * @param list<string> $projection The projection
     *
     * @return AlterTableMutation What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function alterMutation(
        string $sql,
        string $sourceTable,
        string $targetTable,
        TableDefinition $definition,
        array $projection,
    ): AlterTableMutation {
        if ($projection === []) {
            throw new UnsupportedSqlException($sql, 'ALTER TABLE would remove every column');
        }

        return new AlterTableMutation(
            $sql,
            $sourceTable,
            $targetTable,
            $definition,
            $this->registry,
            'SELECT ' . implode(', ', $projection) . ' FROM ' . $this->quote($sourceTable),
        );
    }

    /**
     * Answers the column a name means, as the table spells it.
     *
     * SQLite matches a column name without regard to case, so the name the
     * statement wrote may not be the name the table declared.
     *
     * @param TableDefinition $definition What the table holds
     * @param string $requested The requested
     *
     * @return string|null What it answers
     */
    public function existingColumn(TableDefinition $definition, string $requested): ?string
    {
        foreach ($definition->columns as $column) {
            if (strcasecmp($column, $requested) === 0) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Writes every name as SQLite would write it.
     *
     * @param array<int, string> $columns Columns to read
     *
     * @return list<string> What it answers
     */
    public function quotedColumns(array $columns): array
    {
        $quoted = [];
        foreach ($columns as $column) {
            $quoted[] = $this->quote($column);
        }

        return $quoted;
    }

    /**
     * Writes a name as SQLite would write it.
     *
     * @param string $identifier Name, as it was written
     *
     * @return string What it answers
     */
    public function quote(string $identifier): string
    {
        return (new SqliteIdentifierQuoter())->quote($identifier);
    }

    /**
     * Reads what an ALTER TABLE does, and to what.
     *
     * @param string $sql Statement being read, as written
     *
     * @return array{kind: 'add'|'drop'|'rename_table'|'rename_column', clause: string}|null What it answers
     */
    public function alterOperation(string $sql): ?array
    {
        $stream = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create());
        $tokens = $stream->significantTokens();
        $source = $stream->identifierAt(2);
        if ($source === null) {
            return null;
        }
        $operationIndex = $source['next'];
        $operation = $tokens[$operationIndex] ?? null;
        if ($operation === null) {
            return null;
        }
        if ($operation->isKeyword('ADD')) {
            $clauseIndex = $operationIndex + 1;
            $clauseStart = $tokens[$clauseIndex] ?? null;
            if ($clauseStart !== null && $clauseStart->isKeyword('COLUMN')) {
                ++$clauseIndex;
            }

            return $this->alterClause($sql, $tokens, 'add', $clauseIndex);
        }
        if ($operation->isKeyword('DROP')) {
            $columnKeyword = $tokens[$operationIndex + 1] ?? null;
            if ($columnKeyword === null || !$columnKeyword->isKeyword('COLUMN')) {
                return null;
            }

            return $this->alterClause($sql, $tokens, 'drop', $operationIndex + 2);
        }
        if (!$operation->isKeyword('RENAME')) {
            return null;
        }

        $clauseIndex = $operationIndex + 1;
        $clauseStart = $tokens[$clauseIndex] ?? null;
        if ($clauseStart === null) {
            return null;
        }
        if ($clauseStart->isKeyword('TO')) {
            return $this->alterClause($sql, $tokens, 'rename_table', $clauseIndex + 1);
        }
        if ($clauseStart->isKeyword('COLUMN')) {
            ++$clauseIndex;
        }

        return $this->alterClause($sql, $tokens, 'rename_column', $clauseIndex);
    }

    /**
     * Reads the clause of an ALTER that says what it does.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param 'add'|'drop'|'rename_table'|'rename_column' $kind The kind
     * @param int $startIndex The start index
     *
     * @return array{kind: 'add'|'drop'|'rename_table'|'rename_column', clause: string}|null What it answers
     */
    public function alterClause(string $sql, array $tokens, string $kind, int $startIndex): ?array
    {
        $first = $tokens[$startIndex] ?? null;
        if ($first === null) {
            return null;
        }
        $last = $tokens[count($tokens) - 1];
        $endOffset = $last->kind === SqlTokenKind::Symbol && $last->text === ';'
            ? $last->offset
            : $last->endOffset();

        return [
            'kind' => $kind,
            'clause' => substr($sql, $first->offset, $endOffset - $first->offset),
        ];
    }

    /**
     * Answers the one name a clause names, if it names exactly one.
     *
     * @param string $sql Statement being read, as written
     *
     * @return string|null What it answers
     */
    public function singleIdentifier(string $sql): ?string
    {
        $stream = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create());
        $tokens = $stream->significantTokens();
        $identifier = $stream->identifierAt();

        return $identifier !== null && $identifier['next'] === count($tokens)
            ? $identifier['name']
            : null;
    }

    /**
     * Answers the two names a rename is between.
     *
     * @param string $sql Statement being read, as written
     *
     * @return array{string, string}|null What it answers
     */
    public function renamedIdentifiers(string $sql): ?array
    {
        $stream = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create());
        $tokens = $stream->significantTokens();
        $old = $stream->identifierAt();
        if ($old === null) {
            return null;
        }
        $separator = $tokens[$old['next']] ?? null;
        if ($separator === null || !$separator->isKeyword('TO')) {
            return null;
        }
        $new = $stream->identifierAt($old['next'] + 1);
        if ($new === null || $new['next'] !== count($tokens)) {
            return null;
        }

        return [$old['name'], $new['name']];
    }

    /**
     * Answers a list of columns with one taken out.
     *
     * @param array<int, string> $columns Columns to read
     * @param string $removed The removed
     *
     * @return list<string> What it answers
     */
    public static function withoutColumn(array $columns, string $removed): array
    {
        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => $column !== $removed,
        ));
    }

    /**
     * Answers a map with one key taken out, however it was spelled.
     *
     * @param array<string, T> $map The map
     * @param string $removed The removed
     *
     * @return array<string, T> What it answers
     *
     * @template T
     */
    public static function withoutMapKey(array $map, string $removed): array
    {
        unset($map[$removed]);

        return $map;
    }

    /**
     * Answers a list of columns with one renamed.
     *
     * @param array<int, string> $columns Columns to read
     * @param string $old The old
     * @param string $new The new
     *
     * @return list<string> What it answers
     */
    public static function renamedColumns(array $columns, string $old, string $new): array
    {
        $renamed = [];
        foreach ($columns as $column) {
            $renamed[] = $column === $old ? $new : $column;
        }

        return $renamed;
    }

    /**
     * Answers a foreign key with a renamed column written into it.
     *
     * @param ForeignKeyDefinition $foreignKey The foreign key
     * @param string $old The old
     * @param string $new The new
     *
     * @return ForeignKeyDefinition What it answers
     */
    public static function renamedForeignKey(
        ForeignKeyDefinition $foreignKey,
        string $old,
        string $new,
    ): ForeignKeyDefinition {
        $columns = self::renamedColumns($foreignKey->columns, $old, $new);
        if ($columns === []) {
            return $foreignKey;
        }

        return new ForeignKeyDefinition(
            $columns,
            $foreignKey->referencedTable,
            $foreignKey->referencedColumns,
            $foreignKey->onDelete,
            $foreignKey->onUpdate,
        );
    }

    /**
     * Answers a map with one key renamed, however it was spelled.
     *
     * @param array<string, T> $map The map
     * @param string $old The old
     * @param string $new The new
     *
     * @return array<string, T> What it answers
     *
     * @template T
     */
    public static function renamedMapKey(array $map, string $old, string $new): array
    {
        $renamed = [];
        foreach ($map as $column => $value) {
            $renamed[$column === $old ? $new : $column] = $value;
        }

        return $renamed;
    }
}
