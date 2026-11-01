<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Placement;
use MySqlMemory\Session\Resolution\QueryPreparation;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Raises the first problem the server finds while it reads a statement, before it checks the INTO variables and opens any table.
 *
 * Modern releases look up a nonempty expression collation while parsing; legacy
 * releases resolve it after operand preparation. MySQL 5.6
 * and 5.7 then refuse WITH CUBE (ER_NOT_SUPPORTED_YET), WITH ROLLUP with DISTINCT or ORDER BY in
 * the same block (ER_WRONG_USAGE), then an INTO variable no stored program declares, while they
 * parse the query block, before the problems of a union or a write (verified on live 5.6.51 and
 * 5.7.44 servers). A CAST or CONVERT to TIME or DATETIME with a precision above 6 is found before
 * a wrong call of a native function or a system variable the server does not know, and a table
 * of a multiple-table DELETE that its FROM clause lacks comes after the tables the FROM clause
 * names twice, before any table is opened (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory
 */
final class Reading
{
    /**
     * Raises the error of the first problem the server finds while it reads an operation, in the order the class tells.
     *
     * @throws SqlError When the operation has such a problem
     */
    public function read(MySqlStatement|QueryPreparation $operation, Session $session): void
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        if (Stages::definitionCondition($statement) || (($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Update && ($statement)->hasExecutionCondition())) {
            return;
        }
        if ($operation instanceof MySqlStatement && in_array(($statement)::class, \MySqlMemory\Command\InternalCommand::STATEMENTS, true)) {
            throw (new \MySqlMemory\Command\InternalCommand())->error($operation);
        }
        (new \MySqlMemory\Command\Explain\ExplainCommand())->format($statement);
        if ($session->settings()->release() === GrammarRelease::MySql5651) {
            $this->targets($statement, $session);
        }
        (new \MySqlMemory\Session\Access\DefaultDatabase())->write($statement, $information, $session);
        $this->parsed($operation, $session);
        if ($operation instanceof MySqlStatement) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->check($operation, $session, true);
        }
        $this->targets($statement, $session);
        if ($operation instanceof MySqlStatement) {
            (new \MySqlMemory\Command\Access\HandlerPreparation())->database($operation, $session);
        }
        $this->grouping($operation, $session);
        $this->variables($operation, $session);
        $this->legacyWrites($statement, $information, $session);
        $this->diagnosed($operation, $session);
        (new \MySqlMemory\Session\Access\DefaultDatabase())->calls($statement, $session);
        if ($operation instanceof MySqlStatement && (($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys)) {
            (new \MySqlMemory\Session\Access\DefaultDatabase())->referenced($operation, $session);
        }
        (new \MySqlMemory\Command\Explain\ExplainCommand())->check($statement, $session);
    }

    /**
     * Raises the problems the server finds first of all, while it parses the statement: a name that is too long (see Identifiers), or an unknown character set or collation.
     *
     * @throws SqlError When the operation has such a problem
     */
    public function parsed(MySqlStatement|QueryPreparation $operation, Session $session): void
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        (new Identifiers())->check($statement, $session->settings()->release(), $session);
        foreach (($information)->diagnostics as $diagnostic) {
            if (Stages::characters($diagnostic, $session->settings()->legacy())) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        foreach (($information)->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse && $diagnostic->rule === \SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule::RecursiveReferencePosition) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
    }

    /**
     * Raises the refusals MySQL 5.6 and 5.7 find while they parse a query block: WITH CUBE, WITH ROLLUP with DISTINCT or ORDER BY.
     *
     * @throws SqlError When the operation has such a problem
     */
    public function grouping(MySqlStatement|QueryPreparation $operation, Session $session): void
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        if (!in_array($session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return;
        }
        foreach ((new Walker())->find($statement, Select::class) as $select) {
            if ($select->groupBy?->modifier === GroupingModifier::WithCube) {
                throw StatementError::NotSupportedYet->error('CUBE');
            }
            if ($select->groupBy?->modifier === GroupingModifier::WithRollup && in_array(\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct, $select->options, true)) {
                throw StatementError::WrongUsage->error('WITH ROLLUP', 'DISTINCT');
            }
            if ($select->groupBy?->modifier === GroupingModifier::WithRollup && $select->orderBy !== []) {
                throw StatementError::WrongUsage->error('CUBE/ROLLUP', 'ORDER BY');
            }
        }

    }

    /**
     * Raises the error of a variable no running stored program declares that the INTO or the LIMIT of a query block names, as each block is parsed; MySQL 5.6 and 5.7 then check every INTO of the statement, and the targets of GET DIAGNOSTICS after its condition number (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the operation names such a variable
     */
    public function variables(MySqlStatement|QueryPreparation $operation, Session $session): void
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        $reached = array_fill_keys(array_map(spl_object_id(...), (new \MySqlMemory\Session\Problems())->reached($statement)), true);
        $program = \MySqlMemory\Command\Dispatcher::program($statement);
        if (($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowErrors || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowBinlogEvents || ($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Replication\ShowRelaylogEvents) {
            $this->limited(($statement)->limit, $session);
        }
        if (!$program) {
            (new \MySqlMemory\Session\Access\RelationEntry())->counts($statement, $information, $session);
        }
        (new Placement())->check($statement, $session->settings()->release(), function (Select $select, bool $ended) use ($session, $operation, $reached, $program): void {
            if (!$program && isset($reached[spl_object_id($select)])) {
                $this->declared($select, $ended, $session, $operation);
            }
        }, $reached);
        if (!$program && in_array($session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && !($statement) instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics) {
            $this->into($statement, $session);
        }
    }

    /**
     * Raises the problems SQL Semantics found that the server reports while it reads the statement, in their order: a problem found while parsing, or the alias used twice before a problem found when a clause ends; then a table of a multiple-table DELETE its FROM clause lacks.
     *
     * A parameter of a stored program is left to the program, and an unknown system variable of a
     * statement that checks its own problems to its command.
     *
     * @throws SqlError When the operation has such a problem
     */
    public function diagnosed(MySqlStatement|QueryPreparation $operation, Session $session): void
    {
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        (new \MySqlMemory\Session\Access\RelationEntry())->check($statement, $information, $session);
        $alias = null;
        foreach (($information)->diagnostics as $diagnostic) {
            if (Stages::parsed($diagnostic) && !($diagnostic instanceof UnknownSystemVariable && $operation instanceof MySqlStatement && Stages::selfChecked($operation))) {
                if ($diagnostic instanceof UnknownSystemVariable) {
                    (new \MySqlMemory\Session\Access\EventSchedule())->statement($statement, $session);
                }
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
            if (Stages::closing($diagnostic)) {
                if (Stages::locked($diagnostic)) {
                    (new \MySqlMemory\Session\Access\DefaultDatabase())->referenced($operation, $session);
                }
                throw (new Errors())->error($alias ?? $diagnostic, $session, 'field list', $statement);
            }
            $alias ??= Stages::duplicateAlias($diagnostic, $statement) ? $diagnostic : null;
        }
        if ($alias !== null) {
            throw (new Errors())->error($alias, $session, 'field list', $statement);
        }
        foreach (($information)->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownDeleteTable) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
    }

    /**
     * Raises the error of a variable no running stored program declares that the INTO or the LIMIT of a query block names, where the parser reads it (ER_SP_UNDECLARED_VAR).
     *
     * An INTO written after the select list is read when the block begins; an alias the block gives
     * two tables, its LIMIT, then an INTO written after the query clauses, when it ends, after the
     * blocks written in it; an INTO after the locking clauses is read after their problems (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @throws SqlError When the block names such a variable
     */
    public function declared(Select $select, bool $ended, Session $session, MySqlStatement|QueryPreparation $operation): void
    {
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        if ($ended) {
            $this->aliased($select, $operation, $session);
            foreach ((new Walker())->resolved($select, $information, true) as $query) {
                if ($query instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression || $query instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation) {
                    $this->limited($query->limit, $session);
                }
            }
            $this->limited($select->limit, $session);
        }
        $position = $select->intoPosition;
        if ($select->into instanceof IntoVariables && $position !== \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterLocking && ($position === \SqlSemantics\Platform\MySql\Statement\Query\Into\IntoPosition::AfterItems) !== $ended) {
            foreach ($select->into->targets as $target) {
                if ($target instanceof ProgramVariable && $session->program?->variable($target->name->value) === null) {
                    throw ProgramError::UndeclaredVariable->error($target->name->value);
                }
            }
        }
    }

    /**
     * Raises the error of an alias a query block gives two of its tables (ER_NONUNIQ_TABLE), which the server finds as it reads the FROM clause, before the end of the block.
     *
     * @throws SqlError When the block names a table twice
     */
    public function aliased(Select $select, MySqlStatement|QueryPreparation $operation, Session $session): void
    {
        $information = $operation instanceof QueryPreparation ? $operation->facts : $operation->statementInformation();
        $statement = $operation instanceof QueryPreparation ? $operation->statement : $operation;
        $aliases = [];
        $nodes = $select->from === null ? [] : (new Walker())->find($select->from, Node::class, false);
        foreach ($nodes as $node) {
            $alias = match (true) {
                $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference => $node->alias ?? $node->name->name,
                $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable => $node->alias,
                default => null,
            };
            $aliases[] = $alias === null ? '' : strtolower($alias->value);
        }
        foreach (($information)->diagnostics as $diagnostic) {
            if ($diagnostic instanceof NonUniqueTable && count(array_keys($aliases, strtolower($diagnostic->alias->value), true)) > 1) {
                (new \MySqlMemory\Session\Access\DefaultDatabase())->beforeAlias($nodes, $diagnostic, $session);
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
    }

    /**
     * Raises the error of a LIMIT of a query block naming a variable no running stored program declares (ER_SP_UNDECLARED_VAR), the offset first when it is written first (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @throws SqlError When the LIMIT names such a variable
     */
    public function limited(?\SqlSemantics\Platform\MySql\Statement\Query\Limit $limit, Session $session): void
    {
        if (!$limit instanceof \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit) {
            return;
        }
        foreach ($limit->spelling === \SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Comma ? [$limit->offset, $limit->count] : [$limit->count, $limit->offset] as $operand) {
            if ($operand instanceof ProgramVariable && $session->program?->variable($operand->name->value) === null) {
                throw ProgramError::UndeclaredVariable->error($operand->name->value);
            }
        }
    }

    /**
     * Raises the error of an INTO clause or a GET DIAGNOSTICS target naming a variable no running stored program declares (ER_SP_UNDECLARED_VAR), which the parser finds before any name is resolved (verified on a live 8.4 server).
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
        foreach ((new Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem::class) as $item) {
            if ($item->target instanceof Name && $session?->program?->variable($item->target->value) === null) {
                throw ProgramError::UndeclaredVariable->error($item->target->value);
            }
        }
    }

    /**
     * Raises write preparation refusals before table resolution in MySQL 5.6 and 5.7.
     * Stored definitions retain these conditions until the instruction is invoked.
     * MySQL 5.6 also checks multiple-table UPDATE ordering and limits at this stage.
     *
     * @throws SqlError When the operation has such a problem
     */
    public function legacyWrites(Node $statement, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts, Session $session): void
    {
        $release = $session->settings()->release();
        if (\MySqlMemory\Command\Dispatcher::program($statement) || ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744)) {
            return;
        }
        $early = [\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::NonUpdatableTarget, ...($release === GrammarRelease::MySql5651 ? [\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::LimitedMultipleUpdate, \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::OrderedMultipleUpdate] : [])];
        foreach ($facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse && in_array($diagnostic->rule, $early, true) && $diagnostic->target === null) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $statement);
            }
        }
    }

    /**
     * Resolves the default database and rejects repeated multiple-table DELETE targets.
     *
     * The server reads the list before the tables of FROM or USING, so the error comes before
     * theirs. Two names are the same table when they name the same database, the current one
     * when none is written (verified on a live 8.4 server).
     *
     * @throws SqlError When an unqualified target has no current database or a table is named twice
     */
    public function targets(Node $statement, Session $session): void
    {
        if (!$statement instanceof MultipleDelete) {
            return;
        }
        $seen = [];
        foreach ($statement->targets as $target) {
            if ($target->schema === null && $session->variables->database === '') {
                throw QueryError::NoDatabase->error();
            }
            $key = ($target->schema->value ?? $session->variables->database) . "\0" . $target->name->value;
            if (isset($seen[$key])) {
                throw QueryError::NonUniqueTable->error($target->name->value);
            }
            $seen[$key] = true;
        }
    }

}
