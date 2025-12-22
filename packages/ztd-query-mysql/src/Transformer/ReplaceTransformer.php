<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlParser;
use ZtdQuery\Rewrite\SqlTransformer;

/**
 * Transforms REPLACE statements into SELECT queries that return the replaced rows.
 * Applies CTE shadowing via the SelectTransformer delegate.
 */
final class ReplaceTransformer implements SqlTransformer
{
    private MySqlParser $parser;
    private SelectTransformer $selectTransformer;

    public function __construct(MySqlParser $parser, SelectTransformer $selectTransformer)
    {
        $this->parser = $parser;
        $this->selectTransformer = $selectTransformer;
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $statements = $this->parser->parse($sql);
        if (!isset($statements[0]) || !$statements[0] instanceof ReplaceStatement) {
            throw new UnsupportedSqlException($sql, 'Expected REPLACE statement');
        }

        $statement = $statements[0];

        if ($statement->into === null || $statement->into->dest === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve REPLACE target');
        }

        $dest = $statement->into->dest;
        $tableName = is_string($dest) ? $dest : ($dest->table ?? null);
        if ($tableName === null) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve table name');
        }

        $columns = $statement->into->columns ?? [];
        $columns = array_values(array_filter($columns, 'is_string'));
        if ($columns === [] && isset($tables[$tableName])) {
            $columns = $tables[$tableName]['columns'];
        }
        if ($columns === []) {
            throw new UnsupportedSqlException($sql, 'Cannot determine columns');
        }

        $selectSql = $this->buildReplaceSelect($statement, $columns);
        if ($selectSql === null) {
            throw new UnsupportedSqlException($sql, 'Invalid REPLACE statement');
        }

        return $this->selectTransformer->transform($selectSql, $tables);
    }

    /**
     * Build SELECT SQL from REPLACE statement values.
     *
     * @param ReplaceStatement $statement
     * @param array<int, string> $columns
     * @return string|null
     */
    private function buildReplaceSelect(ReplaceStatement $statement, array $columns): ?string
    {
        if ($statement->values !== null && $statement->values !== []) {
            $rows = [];
            foreach ($statement->values as $valueSet) {
                $props = get_object_vars($valueSet);
                /** @var list<string> $rawStrings */
                $rawStrings = isset($props['raw']) && is_array($props['raw']) ? $props['raw'] : [];
                /** @var list<string> $valStrings */
                $valStrings = isset($props['values']) && is_array($props['values']) ? $props['values'] : [];
                $values = $rawStrings !== [] ? $rawStrings : $valStrings;
                if (count($values) !== count($columns)) {
                    return null;
                }
                $selects = [];
                foreach ($columns as $index => $column) {
                    $expr = trim($values[$index]);
                    $selects[] = $expr . ' AS `' . $column . '`';
                }
                $rows[] = 'SELECT ' . implode(', ', $selects);
            }
            return implode(' UNION ALL ', $rows);
        }

        if ($statement->set !== null && $statement->set !== []) {
            $selects = [];
            foreach ($statement->set as $set) {
                $selects[] = $set->value . ' AS `' . $set->column . '`';
            }
            return 'SELECT ' . implode(', ', $selects);
        }

        if ($statement->select !== null) {
            return $statement->select->build();
        }

        return null;
    }
}
