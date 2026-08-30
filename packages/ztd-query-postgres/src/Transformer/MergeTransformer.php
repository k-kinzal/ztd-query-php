<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Transformer;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlIdentifierQuoter;
use ZtdQuery\Platform\Postgres\Parse\PgSqlMergeParser;
use ZtdQuery\Platform\Postgres\Parse\PgSqlWithPrefix;
use ZtdQuery\Platform\Postgres\Rewrite\PgSqlGeneratedColumnProjector;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeActionKind;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeClause;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeMatchKind;
use ZtdQuery\Platform\Postgres\Statement\PgSqlMergeStatement;
use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Rewrite\ShadowIdentityAllocator;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * The merge transformer, as sql transformer.
 *
 * @phpstan-import-type RenderableValue from ValueRenderer
 */
final class MergeTransformer implements SqlTransformer
{
    private PgSqlIdentifierQuoter $quoter;
    private InsertRowRenderer $rowRenderer;
    private PgSqlGeneratedColumnProjector $generatedColumnProjector;

    /** @readonly */
    private PgSqlWithPrefix $withPrefix;
    private InsertSelectRenderer $insertSelectRenderer;

    /**
     * Binds the instance to what it will work from.
     *
     * @param PgSqlMergeParser $parser
     * @param SelectTransformer $selectTransformer
     */
    public function __construct(
        private readonly PgSqlMergeParser $parser,
        private readonly SelectTransformer $selectTransformer,
    ) {
        $this->quoter = new PgSqlIdentifierQuoter();
        $this->rowRenderer = new InsertRowRenderer();
        $this->generatedColumnProjector = new PgSqlGeneratedColumnProjector();
        $this->withPrefix = new PgSqlWithPrefix();
        $this->insertSelectRenderer = new InsertSelectRenderer();
    }

    /**
     * {@inheritDoc}
     *
     * @throws UnsupportedSqlException
     */
    public function transform(string $sql, array $tables): string
    {
        $statement = $this->parser->parse($sql);
        $table = $tables[$statement->targetTable] ?? null;
        if ($table === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve MERGE target schema');
        }
        if (isset($table['viewSql'])) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve MERGE target schema');
        }

        $columns = $table['columns'];
        if (!array_is_list($columns)) {
            throw new UnsupportedSqlException($sql, 'MERGE target columns must preserve declaration order');
        }
        if ($columns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine MERGE target columns');
        }
        if (($table['storageTable'] ?? $statement->targetTable) !== $statement->targetTable) {
            throw new UnsupportedSqlException($sql, 'MERGE into a child partition is not supported');
        }

        $defaults = $table['columnDefaults'] ?? [];
        $identityStrategies = $table['identityStrategies'] ?? [];
        $existingRows = $table['rows'];
        $generatedExpressions = $table['generatedExpressions'] ?? [];
        $effectiveConditions = $this->effectiveConditions($statement);
        $parts = [];
        $parts[] = $this->unchangedRows($statement, $columns, $effectiveConditions);

        foreach ($statement->clauses as $index => $clause) {
            $effective = $effectiveConditions[$index];
            if ($clause->actionKind === PgSqlMergeActionKind::Update) {
                $parts[] = $this->updatedRows($sql, $statement, $clause, $columns, $defaults, $effective);
            }
            if ($clause->actionKind === PgSqlMergeActionKind::Insert) {
                $parts[] = $this->insertedRows(
                    $sql,
                    $statement,
                    $clause,
                    $columns,
                    $defaults,
                    $identityStrategies,
                    $existingRows,
                    $effective,
                );
            }
        }

        $resultSql = implode(' UNION ALL ', $parts);
        $resultSql = $this->generatedColumnProjector->project($resultSql, $columns, $generatedExpressions);
        $resultSql = $this->withPrefix->carryPrefix($sql, $resultSql);

        return $this->selectTransformer->transform($resultSql, $tables);
    }

    /**
     * Answers what each clause of a MERGE tests, once the match itself is accounted for.
     *
     * @param PgSqlMergeStatement $statement Statement, as the parser reads it
     *
     * @return list<string> What it answers
     */
    public function effectiveConditions(PgSqlMergeStatement $statement): array
    {
        $priorMatched = [];
        $priorNotMatched = [];
        $effective = [];
        foreach ($statement->clauses as $clause) {
            $prior = $clause->matchKind === PgSqlMergeMatchKind::Matched
                ? $priorMatched
                : $priorNotMatched;
            $condition = $clause->conditionSql ?? 'TRUE';
            $predicate = '(' . $condition . ')';
            if ($prior !== []) {
                $excluded = array_map(
                    static fn (string $previous): string => 'COALESCE((' . $previous . '), FALSE)',
                    $prior,
                );
                $predicate .= ' AND NOT (' . implode(' OR ', $excluded) . ')';
            }
            $effective[] = $predicate;
            if ($clause->matchKind === PgSqlMergeMatchKind::Matched) {
                $priorMatched[] = $condition;
            } else {
                $priorNotMatched[] = $condition;
            }
        }

        return $effective;
    }

    /**
     * Answers the rows a MERGE leaves as they were.
     *
     * @param PgSqlMergeStatement $statement Statement, as the parser reads it
     * @param list<string> $columns Columns to read
     * @param list<string> $effectiveConditions The effective conditions
     *
     * @return string What it answers
     */
    public function unchangedRows(
        PgSqlMergeStatement $statement,
        array $columns,
        array $effectiveConditions,
    ): string {
        $qualifier = $this->quoter->quote($statement->targetAlias);
        $selects = [];
        foreach ($columns as $column) {
            $quoted = $this->quoter->quote($column);
            $selects[] = $qualifier . '.' . $quoted . ' AS ' . $quoted;
        }

        $modifications = [];
        foreach ($statement->clauses as $index => $clause) {
            if ($clause->matchKind !== PgSqlMergeMatchKind::Matched
                || !in_array($clause->actionKind, [PgSqlMergeActionKind::Update, PgSqlMergeActionKind::Delete], true)
            ) {
                continue;
            }
            $modifications[] = 'EXISTS (SELECT 1 FROM ' . $statement->sourceSql
                . ' WHERE (' . $statement->joinConditionSql . ') AND (' . $effectiveConditions[$index] . '))';
        }

        $sql = 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $statement->targetSql . ' AS ' . $qualifier;
        if ($modifications !== []) {
            $sql .= ' WHERE NOT (' . implode(' OR ', $modifications) . ')';
        }

        return $sql;
    }

    /**
     * Answers the rows a MERGE changes, as they become.
     *
     * @param string $sql Statement being read, as written
     * @param PgSqlMergeStatement $statement Statement, as the parser reads it
     * @param PgSqlMergeClause $clause The clause
     * @param list<string> $columns Columns to read
     * @param array<string, string> $defaults The defaults
     * @param string $effectiveCondition The effective condition
     *
     * @return string What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function updatedRows(
        string $sql,
        PgSqlMergeStatement $statement,
        PgSqlMergeClause $clause,
        array $columns,
        array $defaults,
        string $effectiveCondition,
    ): string {
        foreach (array_keys($clause->assignments) as $column) {
            if (!in_array($column, $columns, true)) {
                throw new UnsupportedSqlException($sql, 'MERGE UPDATE references an unknown target column');
            }
        }

        $qualifier = $this->quoter->quote($statement->targetAlias);
        $selects = [];
        foreach ($columns as $column) {
            $quoted = $this->quoter->quote($column);
            $expression = $clause->assignments[$column] ?? null;
            if ($expression === null) {
                $expression = $qualifier . '.' . $quoted;
            } elseif (strcasecmp($expression, 'DEFAULT') === 0) {
                $expression = $defaults[$column] ?? 'NULL';
            }
            $selects[] = $expression . ' AS ' . $quoted;
        }

        return 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $statement->targetSql . ' AS ' . $qualifier
            . ' JOIN ' . $statement->sourceSql
            . ' ON (' . $statement->joinConditionSql . ')'
            . ' WHERE ' . $effectiveCondition;
    }

    /**
     * Answers the rows a MERGE writes that were not there.
     *
     * @param string $sql Statement being read, as written
     * @param PgSqlMergeStatement $statement Statement, as the parser reads it
     * @param PgSqlMergeClause $clause The clause
     * @param list<string> $columns Columns to read
     * @param array<string, string> $defaults The defaults
     * @param array<string, \ZtdQuery\Schema\IdentityGenerationStrategy> $identityStrategies The identity strategies
     * @param list<array<string, RenderableValue>> $existingRows The existing rows
     * @param string $effectiveCondition The effective condition
     *
     * @return string What it answers
     *
     * @throws UnsupportedSqlException
     */
    public function insertedRows(
        string $sql,
        PgSqlMergeStatement $statement,
        PgSqlMergeClause $clause,
        array $columns,
        array $defaults,
        array $identityStrategies,
        array $existingRows,
        string $effectiveCondition,
    ): string {
        if ($clause->insertColumns !== []) {
            $sourceColumns = $clause->insertColumns;
        } elseif ($clause->insertValues === []) {
            $sourceColumns = [];
        } else {
            $sourceColumns = $columns;
        }
        foreach ($sourceColumns as $column) {
            if (!in_array($column, $columns, true)) {
                throw new UnsupportedSqlException($sql, 'MERGE INSERT references an unknown target column');
            }
        }

        try {
            $providedExpressions = $this->rowRenderer->providedExpressions($sourceColumns, $clause->insertValues);
        } catch (InvalidDefinitionException $exception) {
            throw new UnsupportedSqlException($sql, 'MERGE INSERT values count does not match column count', $exception);
        }
        $generatedStarts = (new ShadowIdentityAllocator())->allocateSelectStarts(
            $statement->targetTable,
            $identityStrategies,
            array_keys($providedExpressions),
            $existingRows,
        );
        foreach ($generatedStarts as $column => $start) {
            $providedExpressions[$column] = $this->insertSelectRenderer->renderGeneratedIdentity($start);
        }
        $projected = $this->rowRenderer->render($columns, $providedExpressions, $defaults);

        $selects = [];
        foreach ($projected as $column => $expression) {
            $selects[] = $expression . ' AS ' . $this->quoter->quote($column);
        }
        $targetAlias = $this->quoter->quote($statement->targetAlias);

        return 'SELECT ' . implode(', ', $selects)
            . ' FROM ' . $statement->sourceSql
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . $statement->targetSql . ' AS ' . $targetAlias
            . ' WHERE ' . $statement->joinConditionSql . ')'
            . ' AND (' . $effectiveCondition . ')';
    }

}
