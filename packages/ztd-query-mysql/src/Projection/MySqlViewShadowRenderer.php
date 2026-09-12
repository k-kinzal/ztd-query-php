<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Schema\ViewDefinitionSet;

/**
 * Implements the My Sql View Shadow Renderer contract for MySQL.
 */
final class MySqlViewShadowRenderer
{
    /**
     * @param list<string> $tableNames
     * @return array<string, string>
     */
    public function render(ViewDefinitionSet $views, array $tableNames): array
    {
        $definitions = $views->orderedDefinitions();
        $relationNames = array_merge($tableNames, array_keys($definitions));
        $queries = [];
        $relationParser = new MySqlSelectRelationParser();
        foreach ($definitions as $viewName => $definition) {
            $queries[$viewName] = $relationParser->unqualify($definition->query, $relationNames);
        }

        return $queries;
    }
}
