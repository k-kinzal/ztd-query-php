<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Separates declarations retained by MySQL 5.7 from definition-time refusals.
 *
 * Scalar subqueries inside compound expressions are retained. Bare subqueries
 * and query predicates are refused while the definition is read. Forbidden
 * functions, variables and row values without queries remain complete templates.
 * Compound expressions can retain parameter markers for EXECUTE to bind; a bare
 * marker is refused during PREPARE. Observed through MySQL 5.7.44 SQL histories.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LegacyExpressions
{
    /**
     * Determines whether the full expression has a deferred generation role.
     */
    public function contains(Node $expression): bool
    {
        $root = $expression;
        while ($root instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Grouped) {
            $root = $root->operand;
        }
        if ($root instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter) {
            return false;
        }
        if ((new LegacySubqueries())->contains($expression)) {
            return true;
        }
        return !$this->eager($expression) && ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || (new Operands())->problem($expression, new \SqlSemantics\Statement\Identifier\Name('')) !== null);
    }

    /**
     * Identifies predicate queries and stored calls refused while reading a definition.
     */
    public function eager(Node $expression): bool
    {
        if ($expression instanceof Query || ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall && !$expression->native(\SqlSemantics\Contract\GrammarRelease::MySql5744))) {
            return true;
        }
        foreach (get_object_vars($expression) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && $this->eager($child)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Finds retained columns in a complete ALTER action list.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand> $commands The ordered actions
     */
    public function commands(array $commands): bool
    {
        foreach ($commands as $command) {
            $columns = match (true) {
                $command instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn => [$command->definition],
                $command instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn => [$command->column],
                $command instanceof \SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns => $command->elements,
                default => [],
            };
            if ((new LegacySubqueries())->columns($columns)) {
                return true;
            }
        }
        return false;
    }
}
