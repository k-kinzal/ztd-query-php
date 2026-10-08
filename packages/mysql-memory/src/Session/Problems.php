<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
use MySqlMemory\Session\Problem\Locations;
use MySqlMemory\Session\Problem\Stages;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NonGroupedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnpartitionedTable;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
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
 * function a call names, and checks that no window is defined twice last. The stage of each
 * problem is told by Stages, the place of each name by Locations, and the error of each problem
 * by Errors.
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
     * (ER_WRONG_ARGUMENTS), as the server goes on to check it (verified on a live 8.4 server).
     *
     * @throws SqlError When the operation has a problem
     */
    public function raise(Operation $operation, Session $session): void
    {
        if (Stages::selfChecked($operation->statement) || (new \MySqlMemory\Command\Program\ProgramProblems())->raise($operation, $session, $this) || Stages::opensTableFirst($operation->statement)) {
            return;
        }
        $calls = $this->calls($operation);
        $diagnostics = $this->pending($operation, $session);
        $this->read($operation, $session);
        $this->into($operation->statement, $session);
        $this->prepared($operation, $session);
        foreach ($calls as $call) {
            if ($call->named()) {
                throw ProgramError::WrongParametersToStoredFunction->error('`' . $call->name->value . '`');
            }
        }
        $this->paths($operation, $session, $diagnostics);
        $this->opened($operation, $session, $diagnostics);
        (new \MySqlMemory\Hint\Hints())->resolve($operation->statement, $session);
        (new Problem\Delayed())->check($operation->statement, $session);
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
            && !($operation->statement instanceof DropTable && $diagnostic instanceof MissingTable && ($diagnostic->name->schema !== null || $database !== ''))));
    }

    /**
     * Raises the refusals of a write MySQL 5.6 and 5.7 find before they resolve any table: a target that is not updatable, and in 5.6 the ORDER BY or LIMIT of a multiple-table UPDATE, which it refuses while it parses (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the operation has such a problem
     */
    public function legacyWrites(Operation $operation, Session $session): void
    {
        $release = $session->settings()->release();
        if ($release !== \SqlSemantics\Contract\GrammarRelease::MySql5651 && $release !== \SqlSemantics\Contract\GrammarRelease::MySql5744) {
            return;
        }
        $early = [\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::NonUpdatableTarget, ...($release === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? [\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::LimitedMultipleUpdate, \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::OrderedMultipleUpdate] : [])];
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse && in_array($diagnostic->rule, $early, true)) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
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
     * Raises the error of an INTO clause naming a variable no running stored program declares (ER_SP_UNDECLARED_VAR).
     *
     * @throws SqlError When an INTO clause names such a variable
     */
    public function into(Node $statement, ?Session $session = null): void
    {
        foreach ((new Walker())->find($statement, IntoVariables::class) as $into) {
            foreach ($into->targets as $target) {
                if ($target instanceof ProgramVariable && $session?->program?->variable($target->name->value) === null) {
                    throw ProgramError::UndeclaredVariable->error($target->name->value);
                }
            }
        }
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
     * Raises the error of the first problem the server finds while it reads an operation, before it checks the INTO variables and opens any table.
     *
     * A CAST or CONVERT to TIME or DATETIME with a precision above 6 is one of them, found before
     * a wrong call of a native function or a system variable the server does not know. An unknown
     * collation after COLLATE comes first of all, as the server looks it up while it parses the
     * statement, and a table of a multiple-table DELETE that its FROM clause lacks comes after the
     * tables the FROM clause names twice, before any table is opened (verified on a live 8.4
     * server).
     *
     * @throws SqlError When the operation has such a problem
     */
    public function read(Operation $operation, Session $session): void
    {
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownCollation) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
        $this->legacyWrites($operation, $session);
        (new Placement())->check($operation->statement, $session->settings()->release());
        $repeated = $this->repeated($operation->statement);
        if ($repeated !== null) {
            throw QueryError::NonUniqueTable->error($repeated->value);
        }
        $this->targets($operation->statement, $session);
        (new Problem\Precision())->check($operation->statement);
        $alias = null;
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if (\MySqlMemory\Command\Program\ProgramProblems::parameter($operation->statement, $diagnostic)) {
                continue;
            }
            if (Stages::parsed($diagnostic) && !($diagnostic instanceof UnknownSystemVariable && Stages::selfChecked($operation->statement))) {
                if ($diagnostic instanceof UnknownSystemVariable) {
                    (new \MySqlMemory\Command\Program\ProgramProblems())->variables($operation->statement);
                }
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
            if (Stages::closing($diagnostic)) {
                throw (new Errors())->error($alias ?? $diagnostic, $session, 'field list', $operation->statement);
            }
            $alias ??= $diagnostic instanceof NonUniqueTable ? $diagnostic : null;
        }
        if ($alias !== null) {
            throw (new Errors())->error($alias, $session, 'field list', $operation->statement);
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownDeleteTable) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
        (new \MySqlMemory\Command\Show\Inspection())->check($operation->statement, $session);
        (new \MySqlMemory\Command\Explain\ExplainCommand())->check($operation->statement, $session);
    }

    /**
     * Raises the error of a table a multiple-table DELETE names twice in the list of the tables it deletes from (ER_NONUNIQ_TABLE).
     *
     * The server reads the list before the tables of FROM or USING, so the error comes before
     * theirs. Two names are the same table when they name the same database, the current one
     * when none is written (verified on a live 8.4 server).
     *
     * @throws SqlError When the list names a table twice
     */
    public function targets(Node $statement, Session $session): void
    {
        if (!$statement instanceof MultipleDelete) {
            return;
        }
        $seen = [];
        foreach ($statement->targets as $target) {
            $key = ($target->schema->value ?? $session->variables->database) . "\0" . $target->name->value;
            if (isset($seen[$key])) {
                throw QueryError::NonUniqueTable->error($target->name->value);
            }
            $seen[$key] = true;
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
        $references = array_filter((new Walker())->find($statement, Node::class), static fn (Node $node): bool => ($node instanceof TableReference || $node instanceof ExplicitTable) && $node->name()->schema === null);
        $skipped = [];
        foreach ((new Walker())->find($statement, CommonTableExpression::class) as $table) {
            $inside = array_fill_keys(array_map(spl_object_id(...), (new Walker())->find($table, Node::class)), true);
            $used = array_filter($references, static fn (TableReference|ExplicitTable $reference): bool => $reference->name()->name->value === $table->name->value && !isset($inside[spl_object_id($reference)]));
            if ($used === []) {
                $skipped += $inside;
            }
        }

        return array_values(array_filter((new Walker())->find($statement, Node::class), static fn (Node $node): bool => !isset($skipped[spl_object_id($node)])));
    }

    /**
     * Answers the first common table name a WITH clause defines twice, in the order the server parses the definitions: each after the definitions nested in it.
     *
     * The server checks the names while it parses the statement, so a WITH clause inside a common
     * table expression that is never used reports its duplicate too.
     */
    public function repeated(Node $node): ?Name
    {
        $seen = [];
        $properties = get_object_vars($node);
        $children = [];
        array_walk_recursive($properties, static function ($value) use (&$children): void {
            if ($value instanceof Node) {
                $children[] = $value;
            }
        });
        foreach ($children as $child) {
            $found = $this->repeated($child);
            if ($found !== null) {
                return $found;
            }
            if ($node instanceof With && $child instanceof CommonTableExpression) {
                if (isset($seen[$child->name->value])) {
                    return $child->name;
                }
                $seen[$child->name->value] = true;
            }
        }

        return null;
    }
}
