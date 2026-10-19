<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Orders implicit relation databases against preceding system-variable reads.
 *
 * Projection operands precede FROM, and filters follow it. Resolved CTE links
 * identify active definitions without opening unused bodies or consulting SQL.
 *
 * @visibility MySqlMemory
 */
final class RelationEntry
{
    /**
     * Checks modern contextualization before alias and later operand conditions.
     *
     * @throws \MySqlMemory\Error\SqlError When the first reached name cannot bind
     */
    public function check(Node $statement, Facts $facts, Session $session): void
    {
        $database = $statement instanceof M\Utility\Explain\Explain ? $statement->database->value ?? $session->variables->database : $session->variables->database;
        if ($database !== '' || $session->settings()->legacy() || ($statement instanceof \SqlSemantics\Statement\Statement && \MySqlMemory\Command\Dispatcher::program($statement))) {
            return;
        }
        $variables = [];
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof M\Variable\Problem\UnknownSystemVariable && !$problem->assigned && !$problem->structured) {
                $variables[$problem->name] = $problem;
            }
        }
        foreach ($this->nodes($statement, $facts) as $node) {
            if ($node instanceof M\Variable\SystemVariable && $node->instance === null && isset($variables[$node->name->value])) {
                throw (new Errors())->error($variables[$node->name->value], $session);
            }
            if (in_array($node::class, [M\Relation\TableReference::class, M\Query\ExplicitTable::class, M\Dml\WriteTarget::class], true) && $facts->covers($node)) {
                $table = $facts->relation($node)->table;
                if ($table instanceof MissingTable && $table->name->schema === null) {
                    throw QueryError::NoDatabase->error();
                }
            }
        }
    }

    /**
     * @return list<Node> Active occurrences, with projections before input relations
     */
    public function nodes(Node $root, Facts $facts): array
    {
        $pending = [$root];
        $seen = [];
        $found = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[spl_object_id($node)])) {
                continue;
            }
            $seen[spl_object_id($node)] = true;
            $found[] = $node;
            $children = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->children($node);
            if ($node instanceof M\Query\Select || $node instanceof M\Routine\Program\DeferredSelection) {
                $ordered = array_values(array_filter([...$node->items, $node->from, $node->where, $node->groupBy, $node->having, ...$node->windows, $node->qualify, ...$node->orderBy, $node->limit], static fn ($child): bool => $child instanceof Node));
                $children = [...$ordered, ...array_filter($children, static fn (Node $child): bool => !in_array($child, $ordered, true))];
            }
            if (($node instanceof M\Relation\TableReference || $node instanceof M\Query\ExplicitTable) && $facts->covers($node)) {
                $table = $facts->relation($node)->table;
                if ($table instanceof CommonTable) {
                    $children[] = $table->definition;
                }
            }
            array_push($pending, ...array_reverse($children));
        }
        return $found;
    }
}
