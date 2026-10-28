<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Access;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\StatementSemantics;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Orders implicit relation databases against system-variable reads and window counts.
 *
 * Projection operands precede FROM, and filters follow it. Resolved CTE links
 * identify active definitions without opening unused bodies or consulting SQL.
 *
 * @visibility MySqlMemory
 */
final class RelationEntry
{
    /**
     * Resolves contextual names when an active window count or LIMIT requires early lookup.
     * Other statements retain their own alias and declaration ordering.
     *
     * @throws \MySqlMemory\Error\SqlError When a reached count or preceding dependency cannot bind
     */
    public function counts(Node $statement, StatementSemantics $facts, Session $session): void
    {
        foreach ($this->nodes($statement, $facts) as $node) {
            if ($node instanceof M\Call\Window\RoutineVariable || ($node instanceof M\Query\Clause\RowLimit && ($node->count instanceof M\Query\Clause\ProgramVariable || $node->offset instanceof M\Query\Clause\ProgramVariable))) {
                $this->check($statement, $facts, $session, true);
                return;
            }
        }
    }

    /**
     * Checks modern contextualization before alias and later operand conditions.
     *
     * @throws \MySqlMemory\Error\SqlError When the first reached name cannot bind
     */
    public function check(Node $statement, StatementSemantics $facts, Session $session, bool $counts = false): void
    {
        $database = $statement instanceof M\Utility\Explain\Explain ? $statement->database->value ?? $session->variables->database : $session->variables->database;
        $this->maintenance($statement, $facts, $database, $session);
        if ($session->settings()->legacy() || ($statement instanceof \SqlSemantics\Statement\Statement && \MySqlMemory\Command\Dispatcher::program($statement))) {
            return;
        }
        $aliases = $counts ? $this->aliases($facts) : [];
        $variables = $this->variables($facts);
        foreach ($this->nodes($statement, $facts) as $node) {
            if ($counts && $node instanceof M\Query\Clause\RowLimit) {
                (new \MySqlMemory\Session\Problem\Reading())->limited($node, $session);
            }
            if (isset($aliases[spl_object_id($node)])) {
                throw (new Errors())->error($aliases[spl_object_id($node)], $session);
            }
            if ($node instanceof M\Call\Window\RoutineVariable || ($counts && $node instanceof M\Query\Clause\ProgramVariable)) {
                $this->declared($node, $session);
            }
            if ($node instanceof M\Variable\SystemVariable && $node->instance === null && isset($variables[$node->name->value])) {
                throw (new Errors())->error($variables[$node->name->value], $session);
            }
            if ($database === '' && $this->implicit($node, $facts)) {
                throw QueryError::NoDatabase->error();
            }
        }
    }

    /**
     * Registers maintenance targets in order before checking their physical tables.
     */
    public function maintenance(Node $statement, StatementSemantics $facts, string $database, Session $session): void
    {
        if ($statement instanceof M\Server\Maintenance\AnalyzeTable || $statement instanceof M\Server\Maintenance\CheckTable || $statement instanceof M\Server\Maintenance\ChecksumTable || $statement instanceof M\Server\Maintenance\OptimizeTable || $statement instanceof M\Server\Maintenance\RepairTable) {
            $aliases = $this->aliases($facts);
            foreach ($statement->tables as $table) {
                if ($database === '' && $table->name->schema === null) {
                    throw QueryError::NoDatabase->error();
                }
                if (isset($aliases[spl_object_id($table)])) {
                    throw (new Errors())->error($aliases[spl_object_id($table)], $session);
                }
            }
        }
    }

    /**
     * @return array<string, M\Variable\Problem\UnknownSystemVariable> Unresolved unqualified system-variable reads
     */
    public function variables(StatementSemantics $facts): array
    {
        $variables = [];
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof M\Variable\Problem\UnknownSystemVariable && !$problem->assigned && !$problem->structured) {
                $variables[$problem->name] = $problem;
            }
        }
        return $variables;
    }

    /**
     * @return array<int, M\Server\Problem\NonUniqueTable> Duplicate names indexed by their later relation occurrence
     */
    public function aliases(StatementSemantics $facts): array
    {
        $aliases = [];
        foreach ($facts->diagnostics as $problem) {
            if ($problem instanceof M\Server\Problem\NonUniqueTable && $problem->relation !== null) {
                $aliases[spl_object_id($problem->relation)] = $problem;
            }
        }
        return $aliases;
    }

    /**
     * Answers whether this reached base relation needs the current database.
     */
    public function implicit(Node $node, StatementSemantics $facts): bool
    {
        if (!in_array($node::class, [M\Relation\TableReference::class, M\Query\ExplicitTable::class, M\Dml\WriteTarget::class], true) || !$node->hasBinding()) {
            return false;
        }
        $table = $node->relationInformation()->table;
        return $table instanceof MissingTable && $table->name->schema === null;
    }

    /**
     * Requires the active program to declare a count or offset variable.
     *
     * @throws \MySqlMemory\Error\SqlError When the variable has no declaration
     */
    public function declared(M\Call\Window\RoutineVariable|M\Query\Clause\ProgramVariable $variable, Session $session): void
    {
        if ($session->program?->variable($variable->name->value) === null) {
            throw \MySqlMemory\Error\Family\ProgramError::UndeclaredVariable->error($variable->name->value);
        }
    }

    /**
     * @return list<Node> Active occurrences, with projections before input relations
     */
    public function nodes(Node $root, StatementSemantics $facts): array
    {
        /** @var list<array{Node, bool}> $pending */
        $pending = [[$root, false]];
        $seen = [];
        $found = [];
        while ($pending !== []) {
            [$node, $completed] = array_pop($pending);
            if ($completed) {
                $found[] = $node;
                continue;
            }
            if (isset($seen[spl_object_id($node)])) {
                continue;
            }
            $seen[spl_object_id($node)] = true;
            if ($node instanceof M\Relation\DerivedTable) {
                $pending[] = [$node, true];
            } else {
                $found[] = $node;
            }
            $children = (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadDependencies())->children($node);
            if ($node instanceof M\Query\Select || $node instanceof M\Routine\Program\DeferredSelection) {
                $ordered = array_values(array_filter([...$node->items, ...(($node instanceof M\Routine\Program\DeferredSelection || $node->intoPosition === M\Query\Into\IntoPosition::AfterItems) ? [$node->into] : []), $node->from, $node->where, $node->groupBy, $node->having, ...$node->windows, $node->qualify, ...$node->orderBy, $node->limit], static fn ($child): bool => $child instanceof Node));
                $children = [...$ordered, ...array_filter($children, static fn (Node $child): bool => !in_array($child, $ordered, true))];
            }
            if (($node instanceof M\Relation\TableReference || $node instanceof M\Query\ExplicitTable) && $node->hasBinding()) {
                $table = $node->relationInformation()->table;
                if ($table instanceof CommonTable) {
                    $children[] = $table->definition;
                }
            }
            if ($node instanceof M\Query\QueryExpression) {
                $children = array_values(array_filter([$node->body, ...$node->orderBy, $node->limit], static fn ($child): bool => $child instanceof Node));
            }
            foreach (array_reverse($children) as $child) {
                $pending[] = [$child, false];
            }
        }
        return $found;
    }
}
