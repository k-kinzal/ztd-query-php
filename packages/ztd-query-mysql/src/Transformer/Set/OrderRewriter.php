<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer\Set;

use ZtdQuery\Schema\ColumnType;

/**
 * Order Rewriter.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class OrderRewriter
{
    /**
     * Rewrite ORDER BY on SET columns to MySQL-compatible bit-order ranking.
     *
     * @param array<string, array{viewSql: string}|array{columnTypes: array<string, ColumnType>}> $tables
     */
    public function rewriteSetOrderBy(string $sql, array $tables): string
    {
        if (stripos($sql, 'ORDER BY') === false) {
            return $sql;
        }

        [$qualifiedSetMap, $unqualifiedSetMap] = $this->columnMaps($sql, $tables);

        if ($qualifiedSetMap === [] && $unqualifiedSetMap === []) {
            return $sql;
        }

        $rewritten = preg_replace_callback(
            '/\bORDER\s+BY\s+(.+?)(\s+LIMIT\b|\s+FOR\b|\s+LOCK\b|$)/is',
            function (array $matches) use ($qualifiedSetMap, $unqualifiedSetMap): string {
                return $this->rewriteItems($matches, $qualifiedSetMap, $unqualifiedSetMap);
            },
            $sql,
            1
        );

        return $rewritten ?? $sql;
    }
    /**
     * @param array<string, array{viewSql: string}|array{columnTypes: array<string, ColumnType>}> $tables
     * @return array{array<string, list<string>>, array<string, list<string>|null>}
     */
    public function columnMaps(string $sql, array $tables): array
    {
        $qualifiedSetMap = [];
        $unqualifiedSetMap = [];

        foreach ($tables as $tableName => $tableContext) {
            if (isset($tableContext['viewSql'])) {
                continue;
            }
            if (stripos($sql, $tableName) === false) {
                continue;
            }

            $columnTypes = $tableContext['columnTypes'];

            foreach ($columnTypes as $column => $type) {
                $members = (new ValueNormalizer())->extractSetMembers($type->nativeType);
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

        return [$qualifiedSetMap, $unqualifiedSetMap];
    }

    /**
     * @param array{string, string, string} $matches
     * @param array<string, list<string>> $qualifiedSetMap
     * @param array<string, list<string>|null> $unqualifiedSetMap
     */
    public function rewriteItems(array $matches, array $qualifiedSetMap, array $unqualifiedSetMap): string
    {
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

            $rewrittenItems[] = $this->rankExpression($members, $columnRef) . $direction;
        }

        return 'ORDER BY ' . implode(', ', $rewrittenItems) . $matches[2];
    }

    /**
     * @param list<string> $members
     */
    public function rankExpression(array $members, string $columnRef): string
    {
        $rankTerms = [];
        foreach ($members as $index => $member) {
            $bit = 2 ** $index;
            $quotedMember = "'" . str_replace("'", "''", $member) . "'";
            $rankTerms[] = "IF(FIND_IN_SET($quotedMember, $columnRef) > 0, $bit, 0)";
        }

        return '(' . implode(' + ', $rankTerms) . ')';
    }

}
