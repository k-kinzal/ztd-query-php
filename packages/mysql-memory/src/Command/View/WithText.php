<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Dictionary\Routine;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;

/**
 * Writes reached CTE definitions in stored view text.
 *
 * MySQL 8.0.44, 8.4.7 and 9.1.0 omit unused definitions. A used definition
 * followed only by unused definitions leaves a trailing separator; reopening
 * that view fails at its query body. Verified through INFORMATION_SCHEMA.VIEWS,
 * subsequent SELECT and SHOW CREATE VIEW, without inspecting server code.
 *
 * @visibility MySqlMemory
 */
final class WithText
{
    /**
     * @param ViewText $writer The resolved view projection writer
     */
    public function __construct(public readonly ViewText $writer)
    {
    }

    /**
     * Writes the retained definitions and their observed separator placement.
     */
    public function clause(QueryExpression $query): ?string
    {
        $used = $this->used($query);
        if ($used === [] || !$query->with instanceof With) {
            return '';
        }
        $tables = [];
        foreach ($used as $table) {
            $body = $this->writer->query($table->query);
            if ($body === null) {
                return null;
            }
            $tables[] = Routine::quoted($table->name->value) . ($table->columns === [] ? '' : ' (' . implode(',', array_map(static fn ($column): string => Routine::quoted($column->value), $table->columns)) . ')') . ' as (' . $body . ')';
        }
        $trailing = array_key_last($used) !== count($query->with->tables) - 1;
        return 'with ' . ($query->with->recursive ? 'recursive ' : '') . implode(', ', $tables) . ($trailing ? ', ' : '') . ' ';
    }

    /**
     * @return array<int, CommonTableExpression> Reached definitions at their original positions
     */
    public function used(QueryExpression $query): array
    {
        if (!$query->with instanceof With) {
            return [];
        }
        $reached = (new ReadDependencies())->nodes($query);
        return array_filter($query->with->tables, static fn (CommonTableExpression $table): bool => in_array($table, $reached, true));
    }
}
