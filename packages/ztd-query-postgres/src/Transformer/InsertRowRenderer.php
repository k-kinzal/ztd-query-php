<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Transformer;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Rewrite\InsertRowProjectionPlanner;

/**
 * The insert row renderer.
 */
final class InsertRowRenderer
{
    private InsertRowProjectionPlanner $projectionPlanner;

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        $this->projectionPlanner = new InsertRowProjectionPlanner();
    }

    /**
     * @param list<string> $insertColumns
     * @param list<string> $values
     * @return array<string, string>
     *
     * @throws InvalidDefinitionException When the statement cannot describe a row the table would take
     */
    public function providedExpressions(array $insertColumns, array $values): array
    {
        if (count($insertColumns) !== count($values)) {
            throw new InvalidDefinitionException('Insert values count does not match column count.');
        }

        $provided = [];
        foreach ($insertColumns as $index => $column) {
            $expression = trim($values[$index]);
            if (strcasecmp($expression, 'DEFAULT') !== 0) {
                $provided[$column] = $expression;
            }
        }

        return $provided;
    }

    /**
     * @param list<string> $tableColumns
     * @param array<string, string> $providedExpressions
     * @param array<string, string> $defaults
     * @param array<string, int> $generatedIdentityValues
     * @return array<string, string>
     */
    public function render(
        array $tableColumns,
        array $providedExpressions,
        array $defaults,
        array $generatedIdentityValues = [],
    ): array {
        $rendered = [];
        foreach ($this->projectionPlanner->plan($tableColumns, $providedExpressions, $defaults, $generatedIdentityValues) as $projection) {
            $rendered[$projection->targetColumn()] = $projection->providedExpression()
                ?? $projection->defaultExpressionValue()
                ?? ($projection->generatedIdentityValue() !== null ? (string) $projection->generatedIdentityValue() : 'NULL');
        }

        return $rendered;
    }
}
