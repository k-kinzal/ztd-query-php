<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Access\OpenedTables;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Locations;
use MySqlMemory\Session\Problem\Stages;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Raises the first problem SQL Semantics found in a statement as the error the server reports for it.
 *
 * The server reports the problems it finds while it reads the statement first, in the order it
 * reads them: a wrong call of a native function, arguments with aliases, a table alias used
 * twice, a LIMIT operand naming an undeclared variable, and a locking clause naming a table the
 * query lacks or locking a table twice. Then it checks the INTO variables. It then opens the
 * tables, refuses QUALIFY and a CUBE without tables, checks that each window a query names is
 * defined, resolves the names of each clause in order, which includes finding each stored
 * function a call names, and checks that no window is defined twice last. The problems found
 * while the statement is read are raised by Reading, the stage of each problem is told by
 * Stages, the place of each name by Locations, and the error of each problem by Errors.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 *
 * @visibility MySqlMemory
 */
final class Problems
{
    /**
     * Raises the error of the first problem of an operation, if any; an account statement raises its own, in the order its command checks them.
     *
     * A column of MATCH that does not resolve is followed by the error of the AGAINST of the MATCH
     * (ER_WRONG_ARGUMENTS), as the server goes on to check it, and RESIGNAL outside a handler fails
     * before the values it sets are resolved (verified on a live 8.4 server). GET DIAGNOSTICS
     * checks its targets here and resolves its condition number when it runs.
     *
     * @throws SqlError When the operation has a problem
     */
    public function raise(Operation $operation, Session $session): void
    {
        if ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Resignal && ($session->program === null || $session->program->stacked === [])) {
            throw ProgramError::ResignalWithoutHandler->error();
        }
        if ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics) {
            (new Problem\Reading())->into($operation->statement, $session);

            return;
        }
        if (Stages::selfChecked($operation->statement) || (new \MySqlMemory\Command\Program\ProgramProblems())->raise($operation, $session, $this) || Stages::opensTableFirst($operation->statement)) {
            return;
        }
        $calls = $this->calls($operation);
        $diagnostics = $this->pending($operation, $session);
        $reading = new Problem\Reading();
        $reading->read($operation, $session);
        $reading->into($operation->statement, $session);
        $this->prepared($operation, $session);
        foreach ($calls as $call) {
            if ($call->named()) {
                throw ProgramError::WrongParametersToStoredFunction->error('`' . $call->name->value . '`');
            }
        }
        $this->paths($operation, $session, $diagnostics);
        $this->opened($operation, $session, $diagnostics);
        (new Problem\Sampling())->opened($operation->statement, $operation->facts, $session->settings(), $session->instance->dictionary);
        (new \MySqlMemory\Hint\Hints())->resolve($operation->statement, $session);
        (new Problem\IndexHints())->check($operation->statement, $this->reached($operation->statement), $session);
        (new Problem\Delayed())->check($operation->statement, $session);
        $starred = array_filter($diagnostics, static fn (Diagnostic $diagnostic): bool => $diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::StarWithoutTables) !== [];
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 || ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 && !$starred)) {
            $this->analysed($operation->statement);
        }
        [$located, $matched] = (new Locations())->located($operation, $calls, $diagnostics, $session);
        $this->unlocated($diagnostics, $located, $session, $operation->statement);
        $first = Locations::first($located);
        if ($first !== null) {
            throw (new Errors())->located($first[0], $first[1][0], isset($matched[spl_object_id($first[0])]), $session, $operation->statement);
        }
        foreach ($calls as $call) {
            throw (new Errors())->routine($call, $session);
        }
        foreach ($diagnostics as $diagnostic) {
            throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
        }
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $this->analysed($operation->statement);
        }
    }

    /**
     * Raises the error of PROCEDURE ANALYSE in the query an INSERT, a REPLACE or a CREATE TABLE writes, which MySQL 5.6 and 5.7 refuse (ER_WRONG_USAGE) once they have opened the tables, before they resolve the names; 5.6 refuses a `*` without tables first (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the query writes PROCEDURE ANALYSE
     */
    public function analysed(Node $statement): void
    {
        $query = match (true) {
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery => $statement->source,
            $statement instanceof \SqlSemantics\Platform\MySql\Statement\Table\CreateTable => $statement->query,
            default => null,
        };
        foreach ($query === null ? [] : (new Walker())->find($query, Select::class) as $select) {
            if ($select->procedure !== null) {
                throw StatementError::WrongUsage->error('PROCEDURE', 'non-SELECT');
            }
        }
    }

    /**
     * Answers the calls of an operation that name a function the server does not find, leaving out those of a common table expression no table reference names.
     *
     * @return list<FunctionCall>
     */
    public function calls(Operation $operation): array
    {
        $calls = array_values(array_filter((new Walker())->find($operation->statement, FunctionCall::class), static fn (FunctionCall $call): bool => Locations::undeclared($call, $operation)));
        if ($calls !== [] && (new Walker())->find($operation->statement, CommonTableExpression::class) !== []) {
            $reached = array_fill_keys(array_map(spl_object_id(...), $this->reached($operation->statement)), true);
            $calls = array_values(array_filter($calls, static fn (FunctionCall $call): bool => isset($reached[spl_object_id($call)])));
        }

        return $calls;
    }

    /**
     * Answers the problems of an operation the server reports here: not a column outside GROUP BY without ONLY_FULL_GROUP_BY, not one the command of the statement answers, and not a missing table DROP TABLE reports itself.
     *
     * DROP TABLE reports a missing table itself unless it names no database and none is selected.
     *
     * @return list<Diagnostic>
     */
    public function pending(Operation $operation, Session $session): array
    {
        $grouping = $session->modes()->has('ONLY_FULL_GROUP_BY');
        $database = $session->variables->database;
        $tested = $this->tested($operation);

        return array_values(array_filter($operation->facts->diagnostics, static fn (Diagnostic $diagnostic): bool => ($grouping || !$diagnostic instanceof NonGroupedColumn)
            && !($tested && $diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::StarWithoutTables)
            && !Stages::answered($operation->statement, $diagnostic)
            && !Stages::planned($diagnostic)
            && !($operation->statement instanceof DropTable && $diagnostic instanceof MissingTable && ($diagnostic->name->schema !== null || $database !== ''))));
    }

    /**
     * Tells whether every block that writes `*` without tables is a query EXISTS tests, which takes it (verified on live 5.6.51 and 8.4.7 servers).
     */
    public function tested(Operation $operation): bool
    {
        $existing = Locations::existing($operation);
        if ($existing === []) {
            return false;
        }
        foreach ((new Walker())->find($operation->statement, Select::class) as $select) {
            $starred = array_filter($select->items, static fn ($item): bool => $item instanceof \SqlSemantics\Platform\MySql\Statement\Query\Star) !== [];
            if ($starred && ($select->from === null || $select->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual) && !in_array($select, $existing, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Raises the error of the first invalid path of a JSON_TABLE, once the tables are open and before any name is resolved.
     *
     * A table that does not exist, a PARTITION clause of a table without partitions and an unknown
     * partition are found while the tables are opened, before the path.
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     *
     * @throws SqlError When a path of a JSON_TABLE is invalid
     */
    public function paths(Operation $operation, Session $session, array $diagnostics): void
    {
        $path = (new JsonTables())->first($operation->statement);
        if ($path === null) {
            return;
        }
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof MissingTable || $diagnostic instanceof UnpartitionedTable || $diagnostic instanceof UnknownPartition) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }

        throw $path;
    }

    /**
     * Raises the error of the first table the statement reads that does not exist, as the server opens every table of the statement before it resolves any name.
     *
     * The table a statement writes is opened first, and the tables of subqueries in any clause
     * with those of FROM; the tables of a common table expression no table reference names are
     * not (verified on live 8.0, 8.4 and 9.1 servers).
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     *
     * @throws SqlError When a table the statement reads does not exist
     */
    public function opened(Operation $operation, Session $session, array $diagnostics): void
    {
        (new OpenedTables())->prepare($this->reached($operation->statement), $operation->facts, $session);
        $missing = array_values(array_filter($diagnostics, static fn (Diagnostic $diagnostic): bool => $diagnostic instanceof MissingTable));
        if ($missing === []) {
            return;
        }
        $read = [];
        foreach ($this->reached($operation->statement) as $node) {
            if ($node instanceof TableReference || $node instanceof ExplicitTable || $node instanceof WriteTarget) {
                $read[spl_object_id($node->name())] = true;
            }
        }
        foreach ($missing as $diagnostic) {
            if (isset($read[spl_object_id($diagnostic->name)])) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
    }

    /**
     * Raises the problems the server reports before the names it resolves: each problem before the first located one, but a window defined twice, and then a window a query names but does not define.
     *
     * @param list<Diagnostic> $diagnostics The problems of the statement the server reports
     * @param array<int, array{Diagnostic|FunctionCall|\SqlSemantics\Platform\MySql\Statement\Call\ClockCall|SqlError, array{string, list<int>}}> $located The located problems, by object id
     *
     * @throws SqlError When such a problem is found
     */
    public function unlocated(array $diagnostics, array $located, Session $session, Node $statement): void
    {
        foreach ($diagnostics as $diagnostic) {
            if (isset($located[spl_object_id($diagnostic)])) {
                break;
            }
            if (!Stages::late($diagnostic)) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        foreach ($diagnostics as $diagnostic) {
            if ($diagnostic instanceof Misuse && $diagnostic->rule === MisuseRule::UnknownWindow && !isset($located[spl_object_id($diagnostic)])) {
                throw (new Errors())->misuse($diagnostic);
            }
        }
    }

    /**
     * Raises the error of a clause the server refuses once it has opened the tables of the statement, before it resolves any name.
     *
     * GROUP BY CUBE in a statement that reads no table is not supported; with tables, CUBE fails
     * only when the statement runs (Grouping). QUALIFY needs the hypergraph optimizer, which the
     * server does not enable. A table that does not exist is found first, and a query block of a
     * common table expression no table reference names is never prepared (verified on a live 8.4
     * server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
     * https://dev.mysql.com/doc/refman/8.4/en/group-by-modifiers.html.
     *
     * @throws SqlError When the statement has such a clause
     */
    public function prepared(Operation $operation, Session $session): void
    {
        $special = static fn (Node $node): bool => $node instanceof Select && ($node->groupBy?->modifier === GroupingModifier::Cube || $node->qualify !== null);
        if (array_filter((new Walker())->find($operation->statement, Select::class), $special) === []) {
            return;
        }
        $reached = $this->reached($operation->statement);
        $blocks = array_values(array_filter($reached, $special));
        $cube = array_filter($blocks, static fn (Node $block): bool => $block instanceof Select && $block->groupBy?->modifier === GroupingModifier::Cube) !== [];
        $qualify = array_filter($blocks, static fn (Node $block): bool => $block instanceof Select && $block->qualify !== null) !== [];
        if (!$cube && !$qualify) {
            return;
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof MissingTable) {
                throw (new Errors())->error($diagnostic, $session);
            }
        }
        $names = array_map(static fn (CommonTableExpression $table): string => $table->name->value, (new Walker())->find($operation->statement, CommonTableExpression::class));
        $tables = array_filter($reached, static fn (Node $node): bool => ($node instanceof TableReference || $node instanceof ExplicitTable)
            && ($node->name()->schema !== null || !in_array($node->name()->name->value, $names, true)));
        if ($cube && $tables === []) {
            throw StatementError::FeatureNotSupported->error('CUBE');
        }
        if ($qualify) {
            throw StatementError::HypergraphRequired->error('QUALIFY clause');
        }
    }

    /**
     * Answers the nodes of a statement the server prepares: all but those of a common table expression that no table reference outside it names.
     *
     * @return list<Node>
     */
    public function reached(Node $statement): array
    {
        $references = array_filter((new Walker())->find($statement, Node::class), static fn (Node $node): bool => ($node instanceof TableReference || $node instanceof ExplicitTable || $node instanceof WriteTarget) && $node->name()->schema === null);
        $skipped = [];
        foreach ((new Walker())->find($statement, CommonTableExpression::class) as $table) {
            $inside = array_fill_keys(array_map(spl_object_id(...), (new Walker())->find($table, Node::class)), true);
            $used = array_filter($references, static fn (TableReference|ExplicitTable|WriteTarget $reference): bool => strcasecmp($reference->name()->name->value, $table->name->value) === 0 && !isset($inside[spl_object_id($reference)]));
            if ($used === []) {
                $skipped += $inside;
            }
        }

        return array_values(array_filter((new Walker())->find($statement, Node::class), static fn (Node $node): bool => !isset($skipped[spl_object_id($node)])));
    }
}
