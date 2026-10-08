<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ConditionDeclaration;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Relation;

/**
 * One running invocation of a stored program: what is in scope at the statement it runs, and what it answers.
 *
 * The parameters, the local variables, the conditions, the cursors and the handlers are in
 * scope from their declaration to the end of the block that declares them, the innermost last.
 * A procedure answers the result sets of its queries; a statement of a stored function or a
 * trigger, or of a procedure one of them calls, is part of the statement that invokes it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-programs-defining.html,
 * https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html.
 *
 * @visibility MySqlMemory
 */
final class Activation
{
    /**
     * @var list<Row> The parameters, variables and trigger rows in scope, the innermost last
     */
    public array $scope = [];

    /**
     * @var list<Handler> The handlers in scope, the innermost last
     */
    public array $handlers = [];

    /**
     * @var list<ConditionDeclaration> The conditions in scope, the innermost last
     */
    public array $conditions = [];

    /**
     * @var list<Cursor> The cursors in scope, the innermost last
     */
    public array $cursors = [];

    /**
     * @var list<ResultSet> The result sets the procedure answered so far
     */
    public array $results = [];

    /**
     * @var list<Diagnostics> The conditions each running handler handles, the innermost last, which GET STACKED DIAGNOSTICS reads and RESIGNAL raises again
     */
    public array $stacked = [];

    /**
     * @param string $kind PROCEDURE, FUNCTION or TRIGGER
     * @param string $name The database and name of the program, as messages name it
     * @param bool $contained Whether the statements of the program are part of a statement that invokes it: those of a stored function or a trigger, and of a procedure one of them calls
     * @param Collation $collation The collation of the database of the program, which a string variable declared without one takes
     * @param Activation|null $caller The activation of the program that invoked this one, if any
     */
    public function __construct(public readonly string $kind, public readonly string $name, public readonly bool $contained, public readonly Collation $collation, public readonly ?Activation $caller = null)
    {
    }

    /**
     * Answers the rows of names in scope as SQL Semantics reads them.
     *
     * @return list<ProgramRow>
     */
    public function rows(): array
    {
        return array_map(static fn (Row $row): ProgramRow => $row->row(), $this->scope);
    }

    /**
     * Finds the innermost parameter or local variable in scope with a name, compared without regard to letter case.
     */
    public function variable(string $name): ?Variable
    {
        for ($index = count($this->scope) - 1; $index >= 0; $index--) {
            $row = $this->scope[$index];
            $variable = $row->alias === null ? $row->variable($name) : null;
            if ($variable !== null) {
                return $variable;
            }
        }

        return null;
    }

    /**
     * Finds the variable of a name that the innermost row a relation declares holds.
     */
    public function find(Relation $relation, string $name): ?Variable
    {
        for ($index = count($this->scope) - 1; $index >= 0; $index--) {
            if ($this->scope[$index]->relation === $relation) {
                return $this->scope[$index]->variable($name);
            }
        }

        return null;
    }

    /**
     * Finds the NEW or OLD row of a running trigger.
     */
    public function row(string $alias): ?Row
    {
        foreach ($this->scope as $row) {
            if ($row->alias !== null && strcasecmp($row->alias, $alias) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Answers the number of rows, handlers, conditions and cursors in scope, which leave() goes back to.
     *
     * @return array{int, int, int, int}
     */
    public function mark(): array
    {
        return [count($this->scope), count($this->handlers), count($this->conditions), count($this->cursors)];
    }

    /**
     * Leaves the declarations made since a mark, closing the cursors among them.
     *
     * @param array{int, int, int, int} $mark
     */
    public function leave(array $mark): void
    {
        $this->scope = array_slice($this->scope, 0, $mark[0]);
        $this->handlers = array_slice($this->handlers, 0, $mark[1]);
        $this->conditions = array_slice($this->conditions, 0, $mark[2]);
        $this->cursors = array_slice($this->cursors, 0, $mark[3]);
    }

    /**
     * Counts the activations of a program among this one and its callers.
     */
    public function depth(string $kind, string $name): int
    {
        $count = 0;
        for ($activation = $this; $activation !== null; $activation = $activation->caller) {
            if ($activation->kind === $kind && strcasecmp($activation->name, $name) === 0) {
                $count++;
            }
        }

        return $count;
    }
}
