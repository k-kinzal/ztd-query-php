<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Placement;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\UnknownDeleteTable;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\GroupingModifier;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProgramVariable;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;

/**
 * Raises the first problem the server finds while it reads a statement, before it checks the INTO variables and opens any table.
 *
 * An unknown collation after COLLATE comes first of all, as the server looks it up while it
 * parses the statement, and so does a key part of length 0 (ER_KEY_PART_0) after it. MySQL 5.6
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
    public function read(Operation $operation, Session $session): void
    {
        (new \MySqlMemory\Command\Explain\ExplainCommand())->format($operation->statement);
        $this->parsed($operation, $session);
        $this->grouping($operation, $session);
        $this->variables($operation, $session);
        $this->legacyWrites($operation, $session);
        $repeated = $this->repeated($operation->statement);
        if ($repeated !== null) {
            throw QueryError::NonUniqueTable->error($repeated->value);
        }
        $this->targets($operation->statement, $session);
        (new Precision())->check($operation->statement);
        $this->diagnosed($operation, $session);
        (new \MySqlMemory\Command\Show\Inspection())->check($operation->statement, $session);
        (new \MySqlMemory\Command\Explain\ExplainCommand())->check($operation->statement, $session);
    }

    /**
     * Raises the problems the server finds first of all, while it parses the statement: a name that is too long (see Identifiers), an unknown collation after COLLATE, then a key part of length 0 (ER_KEY_PART_0).
     *
     * @throws SqlError When the operation has such a problem
     */
    public function parsed(Operation $operation, Session $session): void
    {
        (new EngineAttributes())->check($operation->statement);
        (new Identifiers())->check($operation->statement, $session->settings()->release());
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownCollation || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
        foreach ((new Walker())->find($operation->statement, \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart::class) as $part) {
            if ($part->length !== null && !$part->length->hexadecimal && (int) $part->length->text === 0) {
                throw \MySqlMemory\Error\Family\SchemaError::KeyPartZeroLength->error($part->column->value);
            }
        }
    }

    /**
     * Raises the refusals MySQL 5.6 and 5.7 find while they parse a query block: WITH CUBE, WITH ROLLUP with DISTINCT or ORDER BY, and INTO in the query of a view.
     *
     * @throws SqlError When the operation has such a problem
     */
    public function grouping(Operation $operation, Session $session): void
    {
        if (!in_array($session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)) {
            return;
        }
        foreach ((new Walker())->find($operation->statement, Select::class) as $select) {
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
        if ($operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView || $operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\View\AlterView) {
            (new \MySqlMemory\Command\Program\ProgramProblems())->clause($operation->statement, $session);
        }
    }

    /**
     * Raises the error of a variable no running stored program declares that the INTO or the LIMIT of a query block names, as each block is parsed; MySQL 5.6 and 5.7 then check every INTO of the statement, and the targets of GET DIAGNOSTICS after its condition number (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the operation names such a variable
     */
    public function variables(Operation $operation, Session $session): void
    {
        $reached = array_fill_keys(array_map(spl_object_id(...), (new \MySqlMemory\Session\Problems())->reached($operation->statement)), true);
        $program = \MySqlMemory\Command\Dispatcher::program($operation->statement);
        foreach ($program ? [] : (new Walker())->find($operation->statement, \SqlSemantics\Platform\MySql\Statement\Call\Window\RoutineVariable::class) as $variable) {
            if ($session->program?->variable($variable->name->value) === null) {
                throw ProgramError::UndeclaredVariable->error($variable->name->value);
            }
        }
        (new Placement())->check($operation->statement, $session->settings()->release(), function (Select $select, bool $ended) use ($session, $operation, $reached, $program): void {
            if (!$program && isset($reached[spl_object_id($select)])) {
                $this->declared($select, $ended, $session, $operation);
            }
        });
        if (in_array($session->settings()->release(), [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) && !$operation->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics) {
            $this->into($operation->statement, $session);
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
    public function diagnosed(Operation $operation, Session $session): void
    {
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
    public function declared(Select $select, bool $ended, Session $session, Operation $operation): void
    {
        if ($ended) {
            $this->aliased($select, $operation, $session);
            $this->limited($select, $session);
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
    public function aliased(Select $select, Operation $operation, Session $session): void
    {
        $aliases = [];
        foreach ($select->from === null ? [] : (new Walker())->find($select->from, Node::class, false) as $node) {
            $alias = match (true) {
                $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableReference => $node->alias ?? $node->name->name,
                $node instanceof \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable => $node->alias,
                default => null,
            };
            $aliases[] = $alias === null ? '' : strtolower($alias->value);
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof NonUniqueTable && count(array_keys($aliases, strtolower($diagnostic->alias->value), true)) > 1) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
    }

    /**
     * Raises the error of a LIMIT of a query block naming a variable no running stored program declares (ER_SP_UNDECLARED_VAR), the offset first when it is written first (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     *
     * @throws SqlError When the LIMIT names such a variable
     */
    public function limited(Select $select, Session $session): void
    {
        $limit = $select->limit;
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
     * Raises the refusals of a write MySQL 5.6 and 5.7 find before they resolve any table: a target that is not updatable, and in 5.6 the ORDER BY or LIMIT of a multiple-table UPDATE, which it refuses while it parses (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws SqlError When the operation has such a problem
     */
    public function legacyWrites(Operation $operation, Session $session): void
    {
        $release = $session->settings()->release();
        if ($release !== GrammarRelease::MySql5651 && $release !== GrammarRelease::MySql5744) {
            return;
        }
        $early = [\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::NonUpdatableTarget, ...($release === GrammarRelease::MySql5651 ? [\SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::LimitedMultipleUpdate, \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteRule::OrderedMultipleUpdate] : [])];
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Dml\Problem\WriteMisuse && in_array($diagnostic->rule, $early, true)) {
                throw (new Errors())->error($diagnostic, $session, 'field list', $operation->statement);
            }
        }
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
