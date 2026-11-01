<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Placement;

use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Finds a set function or window function written where a standalone statement cannot evaluate it.
 *
 * Rule: MYSQL-STATEMENT-CALLS-001. Outside a query block the server refuses a set function that
 * aggregates ("Invalid use of group function") and a window function ("You cannot use the window
 * function ... in this context"), and a named window nobody defines ("Window name ... is not defined")
 * wherever it is written, including DO and the select list of a query without tables. The server
 * finds these once a statement that opens no table is read; a stored program body keeps them for the
 * run, so the body of a routine, trigger or event definition is not read (verified on 5.7.44 and 8.4.7).
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class StatementCalls
{
    /**
     * Tells whether the statement evaluates its operands without opening a table, so the server checks
     * the calls once it is read: KILL, PURGE BINARY LOGS BEFORE, SET, INSTALL COMPONENT and SHOW filters.
     */
    public function detached(Node $statement): bool
    {
        return $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill
            || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Replication\Log\PurgeLogsBefore
            || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables
            || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent
            || str_starts_with($statement::class, 'SqlSemantics\\Platform\\MySql\\Statement\\Utility\\Show\\');
    }

    /**
     * Answers the first misplaced call of a standalone statement, or null.
     *
     * A set function or window function outside every query block is a problem only in a detached
     * statement. A named window that no enclosing query block defines is a problem in a detached
     * statement, in DO, and in a query block without tables; a query block that reads tables resolves
     * its windows after its tables, which MemoryDB checks.
     */
    public function problem(Node $statement): ?Misuse
    {
        $detached = $this->detached($statement);
        /** @var list<array{Node, string}> $pending */
        $pending = [[$statement, $statement instanceof Evaluation || $statement instanceof DeferredEvaluation ? 'evaluation' : 'statement']];
        while ($pending !== []) {
            [$node, $context] = array_pop($pending);
            if ($node instanceof Select) {
                $problem = ($node->from === null || $node->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual) ? (new \SqlSemantics\Platform\MySql\Rules\Query\Window\Names())->problem($node) : null;
                if ($problem !== null) {
                    return $problem;
                }
            }
            if ($node instanceof Query) {
                $context = 'query';
            } elseif ($node instanceof Evaluation || $node instanceof DeferredEvaluation) {
                $context = 'evaluation';
            }
            if ($context !== 'query' && ($node instanceof SetFunction || $node instanceof WindowFunction)) {
                $over = $node instanceof WindowFunction ? $node->over : $node->over();
                if ($over instanceof Name) {
                    return new Misuse(MisuseRule::UnknownWindow, $over);
                }
                if ($context === 'statement' && $detached) {
                    return $node instanceof SetFunction && $node->aggregates()
                        ? new Misuse(MisuseRule::AggregateOutsideQuery)
                        : new Misuse(MisuseRule::WindowOutsideQuery, new Name($this->name($node)));
                }
            }
            if ($context === 'query' && !$node instanceof Query) {
                continue;
            }
            $stored = $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent || $node instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
            foreach (get_object_vars($node) as $property => $value) {
                if ($stored && $property === 'body') {
                    continue;
                }
                foreach (is_array($value) ? $value : [$value] as $child) {
                    if ($child instanceof Node) {
                        $pending[] = [$child, $context];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Names a set or window function as the server writes it in its context error.
     */
    public function name(SetFunction|WindowFunction $call): string
    {
        return strtolower(match (true) {
            $call instanceof WindowFunction => $call->kind->value,
            $call instanceof \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate => $call->function->value,
            $call instanceof \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat => 'group_concat',
            default => 'json_objectagg',
        });
    }
}
