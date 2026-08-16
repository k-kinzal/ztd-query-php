<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Platform\IdentifierQuoter;

final class InsertSelectProjector
{
    public function __construct(
        private readonly IdentifierQuoter $identifierQuoter,
        private readonly InsertRowProjector $rowProjector = new InsertRowProjector(),
        private readonly ?SelectListAliaser $selectListAliaser = null,
    ) {
    }

    /**
     * @param list<string> $tableColumns
     * @param list<string> $insertColumns
     * @param array<string, string> $defaults
     * @param array<string, string> $generatedValues
     */
    public function project(
        string $selectSql,
        array $tableColumns,
        array $insertColumns,
        array $defaults,
        array $generatedValues = [],
    ): string {
        if ($this->selectListAliaser !== null) {
            $selectSql = $this->selectListAliaser->alias($selectSql, $this->identifierQuoter);
        }
        $sourceColumns = [];
        $sourceExpressions = [];
        foreach ($insertColumns as $index => $column) {
            $sourceColumn = '__ztd_insert_' . $index;
            $sourceColumns[] = $this->identifierQuoter->quote($sourceColumn);
            $sourceExpressions[] = $this->identifierQuoter->quote($sourceColumn);
        }

        $projected = $this->rowProjector->project(
            $tableColumns,
            $insertColumns,
            $sourceExpressions,
            $defaults,
            $generatedValues,
        );
        $selects = [];
        foreach ($projected as $column => $expression) {
            $selects[] = $expression . ' AS ' . $this->identifierQuoter->quote($column);
        }

        $sourceName = $this->identifierQuoter->quote('__ztd_insert_source');

        return 'WITH ' . $sourceName . ' (' . implode(', ', $sourceColumns) . ') AS ('
            . $selectSql . ') SELECT ' . implode(', ', $selects) . ' FROM ' . $sourceName;
    }
}
