<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Applies CTE shadowing to SELECT statements.
 *
 * Generates WITH clauses that shadow referenced tables using in-memory data,
 * rewrites SET column ORDER BY for correct bit-order ranking.
 */
final class SelectTransformer implements SqlTransformer
{
    private CastRenderer $castRenderer;
    private IdentifierQuoter $quoter;

    public function __construct(?CastRenderer $castRenderer = null, ?IdentifierQuoter $quoter = null)
    {
        $this->castRenderer = $castRenderer ?? new MySqlCastRenderer();
        $this->quoter = $quoter ?? new MySqlIdentifierQuoter();
    }

    /**
     * {@inheritDoc}
     */
    public function transform(string $sql, array $tables): string
    {
        $sql = $this->rewriteSetOrderBy($sql, $tables);

        $ctes = [];
        foreach ($tables as $tableName => $tableContext) {
            if (stripos($sql, $tableName) === false) {
                continue;
            }

            $rows = $tableContext['rows'];
            $columns = $tableContext['columns'];
            $columnTypes = $tableContext['columnTypes'];

            if ($columns === [] && $rows !== []) {
                $columns = array_keys($rows[0]);
                foreach ($rows as $row) {
                    foreach (array_keys($row) as $column) {
                        if (!in_array($column, $columns, true)) {
                            $columns[] = $column;
                        }
                    }
                }
            }

            if ($columns === [] && $rows === []) {
                continue;
            }

            $ctes[] = $this->generateCte($tableName, $rows, $columns, $columnTypes);
        }

        if ($ctes === []) {
            return $sql;
        }

        $cteString = implode(",\n", $ctes);
        $pattern = '/^(\s*(?:(?:\/\*.*?\*\/)|(?:--.*?\n)|(?:#.*?\n)|\s)*)WITH\b/is';
        if (preg_match($pattern, $sql) === 1) {
            return (string) preg_replace($pattern, '$1WITH ' . $cteString . ",\n", $sql, 1);
        }

        return "WITH $cteString\n$sql";
    }

    /**
     * Generate a CTE fragment for a single table.
     *
     * @param string $tableName
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $columns
     * @param array<string, ColumnType> $columnTypes
     * @return string
     */
    private function generateCte(
        string $tableName,
        array $rows,
        array $columns,
        array $columnTypes
    ): string {
        $quotedTable = $this->quoter->quote($tableName);

        if ($columns !== []) {
            if ($rows === []) {
                $selects = [];
                foreach ($columns as $col) {
                    $type = $columnTypes[$col] ?? null;
                    $nullCast = $type !== null
                        ? $this->castRenderer->renderNullCast($type)
                        : $this->renderFallbackNullCast();
                    $selects[] = "$nullCast AS " . $this->quoter->quote($col);
                }
                return "$quotedTable AS (SELECT " . implode(", ", $selects) . " FROM DUAL WHERE 0)";
            }

            $ctes = [];
            foreach ($rows as $row) {
                $selects = [];
                foreach ($columns as $col) {
                    $type = $columnTypes[$col] ?? null;
                    $valStr = $this->formatValue($row[$col] ?? null, $type);
                    $selects[] = "$valStr AS " . $this->quoter->quote($col);
                }
                $ctes[] = "SELECT " . implode(", ", $selects);
            }

            $union = implode(" UNION ALL ", $ctes);
            return "$quotedTable AS ($union)";
        }

        if ($rows === []) {
            throw new \RuntimeException("Cannot shadow table '$tableName' with empty data (columns unknown).");
        }

        $ctes = [];
        foreach ($rows as $row) {
            $selects = [];
            foreach ($row as $col => $val) {
                $colName = $col;
                $type = $columnTypes[$colName] ?? null;
                $valStr = $this->formatValue($val, $type);
                $selects[] = "$valStr AS " . $this->quoter->quote($colName);
            }
            $ctes[] = "SELECT " . implode(", ", $selects);
        }

        $union = implode(" UNION ALL ", $ctes);
        return "$quotedTable AS ($union)";
    }

    private function formatValue(mixed $val, ?ColumnType $type = null): string
    {
        if (is_null($val)) {
            return "NULL";
        }

        if ($type !== null) {
            return $this->formatWithColumnType($val, $type);
        }

        if (is_int($val)) {
            return $this->castRenderer->renderCast(
                (string) $val,
                new ColumnType(ColumnTypeFamily::INTEGER, 'INT'),
            );
        }
        if (is_string($val)) {
            return $this->castRenderer->renderCast(
                $this->quoteValue($val),
                new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR'),
            );
        }
        if (is_bool($val)) {
            return $val ? "TRUE" : "FALSE";
        }
        if (is_float($val)) {
            return (string) $val;
        }
        if (is_object($val) && method_exists($val, '__toString')) {
            return (string) $val;
        }
        throw new \RuntimeException('Unsupported value type for CTE shadowing.');
    }

    private function formatWithColumnType(mixed $val, ColumnType $type): string
    {
        $strVal = is_scalar($val) ? (string) $val : ($val === null ? '' : serialize($val));

        if ($type->family === ColumnTypeFamily::STRING && str_starts_with(strtoupper($type->nativeType), 'SET(')) {
            $strVal = $this->normalizeSetValue($strVal, $type->nativeType);
        }

        $quotedVal = $this->quoteValue($strVal);

        return $this->castRenderer->renderCast($quotedVal, $type);
    }

    private function renderFallbackNullCast(): string
    {
        return $this->castRenderer->renderNullCast(
            new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR'),
        );
    }

    private function quoteValue(string $val): string
    {
        return "'" . str_replace("'", "''", $val) . "'";
    }

    private function normalizeSetValue(string $value, string $mysqlType): string
    {
        if ($value === '') {
            return $value;
        }

        if (preg_match("/^SET\\((.*)\\)$/i", trim($mysqlType), $matches) !== 1) {
            return $value;
        }

        $definition = $matches[1];
        $declared = [];
        if (preg_match_all('/\'((?:\'\'|[^\'])*)\'|"((?:""|[^"])*)"/', $definition, $valueMatches, PREG_SET_ORDER) > 0) {
            foreach ($valueMatches as $tokenMatch) {
                $singleQuoted = $tokenMatch[1] ?? '';
                $doubleQuoted = $tokenMatch[2] ?? '';
                if ($singleQuoted !== '') {
                    $declared[] = str_replace("''", "'", $singleQuoted);
                    continue;
                }
                $declared[] = str_replace('""', '"', $doubleQuoted);
            }
        } else {
            foreach (explode(',', $definition) as $token) {
                $trimmed = trim($token, " \t\n\r\0\x0B'\"");
                if ($trimmed !== '') {
                    $declared[] = $trimmed;
                }
            }
        }

        if ($declared === []) {
            return $value;
        }

        $rank = [];
        foreach ($declared as $index => $name) {
            $rank[$name] = $index;
        }

        $parts = explode(',', $value);
        $normalized = [];
        foreach ($parts as $part) {
            $candidate = trim($part);
            if ($candidate === '' || !array_key_exists($candidate, $rank)) {
                continue;
            }
            $normalized[$candidate] = true;
        }

        if ($normalized === []) {
            return $value;
        }

        $members = array_keys($normalized);
        usort(
            $members,
            static fn (string $a, string $b): int => $rank[$a] <=> $rank[$b]
        );

        return implode(',', $members);
    }

    /**
     * Rewrite ORDER BY on SET columns to MySQL-compatible bit-order ranking.
     *
     * @param array<string, array{
     *     rows: array<int, array<string, mixed>>,
     *     columns: array<int, string>,
     *     columnTypes: array<string, ColumnType>
     * }> $tables
     */
    private function rewriteSetOrderBy(string $sql, array $tables): string
    {
        if (stripos($sql, 'ORDER BY') === false) {
            return $sql;
        }

        $qualifiedSetMap = [];
        $unqualifiedSetMap = [];

        foreach ($tables as $tableName => $tableContext) {
            if (stripos($sql, $tableName) === false) {
                continue;
            }

            $columnTypes = $tableContext['columnTypes'];

            foreach ($columnTypes as $column => $type) {
                $members = $this->extractSetMembers($type->nativeType);
                if ($members === []) {
                    continue;
                }

                $qualifiedSetMap["`$tableName`.`$column`"] = $members;

                if (!array_key_exists($column, $unqualifiedSetMap)) {
                    $unqualifiedSetMap[$column] = $members;
                    continue;
                }

                if ($unqualifiedSetMap[$column] !== $members) {
                    $unqualifiedSetMap[$column] = null;
                }
            }
        }

        if ($qualifiedSetMap === [] && $unqualifiedSetMap === []) {
            return $sql;
        }

        $rewritten = preg_replace_callback(
            '/\bORDER\s+BY\s+(.+?)(\s+LIMIT\b|\s+FOR\b|\s+LOCK\b|$)/is',
            function (array $matches) use ($qualifiedSetMap, $unqualifiedSetMap): string {
                $orderByClause = trim($matches[1]);
                if ($orderByClause === '') {
                    return $matches[0];
                }

                $splitResult = preg_split('/\s*,\s*/', $orderByClause);
                $items = $splitResult !== false ? $splitResult : [$orderByClause];
                $rewrittenItems = [];

                foreach ($items as $item) {
                    $trimmed = trim($item);

                    if (preg_match('/^(?:(`[^`]+`)\.)?(`(?<column>[^`]+)`)(?<direction>\s+(?:ASC|DESC))?$/i', $trimmed, $parts) !== 1) {
                        $rewrittenItems[] = $trimmed;
                        continue;
                    }

                    $qualifier = $parts[1];
                    $columnToken = $parts[2];
                    $column = $parts['column'];
                    $direction = $parts['direction'] ?? '';

                    $columnRef = $qualifier !== '' ? "$qualifier.$columnToken" : $columnToken;
                    $qualifiedKey = $qualifier !== '' ? "$qualifier.$columnToken" : '';

                    $members = null;
                    if ($qualifiedKey !== '' && array_key_exists($qualifiedKey, $qualifiedSetMap)) {
                        $members = $qualifiedSetMap[$qualifiedKey];
                    } elseif (array_key_exists($column, $unqualifiedSetMap) && $unqualifiedSetMap[$column] !== null) {
                        $members = $unqualifiedSetMap[$column];
                    }

                    if ($members === null) {
                        $rewrittenItems[] = $trimmed;
                        continue;
                    }

                    $rankTerms = [];
                    foreach ($members as $index => $member) {
                        $bit = 2 ** $index;
                        $quotedMember = "'" . str_replace("'", "''", $member) . "'";
                        $rankTerms[] = "IF(FIND_IN_SET($quotedMember, $columnRef) > 0, $bit, 0)";
                    }

                    $rewrittenItems[] = '(' . implode(' + ', $rankTerms) . ')' . $direction;
                }

                return 'ORDER BY ' . implode(', ', $rewrittenItems) . $matches[2];
            },
            $sql,
            1
        );

        return $rewritten ?? $sql;
    }

    /**
     * @return list<string>
     */
    private function extractSetMembers(string $type): array
    {
        if (preg_match('/^SET\((.*)\)$/i', trim($type), $matches) !== 1) {
            return [];
        }

        $definition = $matches[1];
        $members = [];

        if (preg_match_all('/\'((?:\'\'|[^\'])*)\'|"((?:""|[^"])*)"/', $definition, $valueMatches, PREG_SET_ORDER) > 0) {
            foreach ($valueMatches as $tokenMatch) {
                $singleQuoted = $tokenMatch[1] ?? '';
                $doubleQuoted = $tokenMatch[2] ?? '';
                if ($singleQuoted !== '') {
                    $members[] = str_replace("''", "'", $singleQuoted);
                    continue;
                }
                $members[] = str_replace('""', '"', $doubleQuoted);
            }

            return $members;
        }

        foreach (explode(',', $definition) as $token) {
            $trimmed = trim($token, " \t\n\r\0\x0B'\"");
            if ($trimmed !== '') {
                $members[] = $trimmed;
            }
        }

        return $members;
    }
}
