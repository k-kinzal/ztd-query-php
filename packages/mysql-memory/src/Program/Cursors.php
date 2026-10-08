<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Command\Output;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Problems;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CloseCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\FetchCursor;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\OpenCursor;
use SqlSemantics\Statement\Identifier\Name;
use WeakReference;

/**
 * Runs OPEN, FETCH and CLOSE of the cursors of a stored program.
 *
 * OPEN runs the query of the cursor and keeps its rows; a cursor open already is
 * ER_SP_CURSOR_ALREADY_OPEN. FETCH stores the next row into the variables it names, each value
 * as into a column of the variable's type; another number of variables than of columns is
 * ER_SP_WRONG_NO_OF_FETCH_ARGS, and a cursor past its last row raises the error ER_SP_FETCH_NO_DATA,
 * a NOT FOUND condition. FETCH and CLOSE of a cursor that is not open are ER_SP_CURSOR_NOT_OPEN.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/cursors.html,
 * https://dev.mysql.com/doc/refman/8.4/en/fetch.html.
 *
 * @visibility MySqlMemory
 */
final class Cursors
{
    /**
     * @param Statements $statements The statements of the running program
     */
    public function __construct(public readonly Statements $statements)
    {
    }

    /**
     * Runs the statement.
     *
     * @throws SqlError When the cursor is not in the state the statement needs, or its query fails
     */
    public function run(OpenCursor|FetchCursor|CloseCursor $statement): void
    {
        $cursor = $this->cursor($statement->cursor);
        if ($statement instanceof OpenCursor) {
            $this->open($cursor);

            return;
        }
        if ($cursor->rows === null) {
            throw ProgramError::CursorNotOpen->error();
        }
        if ($statement instanceof CloseCursor) {
            $cursor->rows = null;

            return;
        }
        $this->fetch($cursor, $statement);
    }

    /**
     * Finds the innermost cursor in scope with a name.
     *
     * @throws SqlError When no cursor of the name is in scope
     */
    public function cursor(Name $name): Cursor
    {
        $cursors = $this->statements->activation->cursors;
        for ($index = count($cursors) - 1; $index >= 0; $index--) {
            if (strcasecmp($cursors[$index]->declaration->name->value, $name->value) === 0) {
                return $cursors[$index];
            }
        }

        throw ProgramError::UndefinedCursor->error($name->value);
    }

    /**
     * Runs the query of a cursor and keeps its rows.
     *
     * @throws SqlError When the cursor is open already or the query fails
     */
    public function open(Cursor $cursor): void
    {
        if ($cursor->rows !== null) {
            throw ProgramError::CursorAlreadyOpen->error();
        }
        $session = $this->statements->session;
        $query = $cursor->declaration->query;
        if (!$query instanceof \SqlSemantics\Statement\Statement) {
            throw \MySqlMemory\Error\Family\StatementError::NotSupportedYet->error('a cursor over ' . $query::class);
        }
        $operation = $this->statements->operation($query);
        $session->diagnostics->clear();
        (new Problems())->raise($operation, $session);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, microtime(true));
        $connection = new Connection($session->variables, $context, $session->user, $session->host, $session->id, [], $this->statements->activation, WeakReference::create($session));
        $plan = (new Planner($query, $operation->facts, $session->settings(), $connection, $session->instance->dictionary))->query($query, null);
        $cursor->rows = (new Output())->result($plan, $context, false)->rows;
        $cursor->domains = $plan->domains;
        $cursor->position = 0;
    }

    /**
     * Stores the next row of a cursor into the variables FETCH names.
     *
     * @throws SqlError When the number of variables is wrong, no row is left, or a value is refused
     */
    public function fetch(Cursor $cursor, FetchCursor $statement): void
    {
        $session = $this->statements->session;
        $session->diagnostics->clear();
        if (count($statement->targets) !== count($cursor->domains)) {
            throw ProgramError::WrongFetchCount->error();
        }
        $row = $cursor->rows[$cursor->position] ?? null;
        if ($row === null) {
            throw ProgramError::NoData->error();
        }
        $cursor->position++;
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, microtime(true));
        $context->strict = $context->modes->strict();
        foreach ($statement->targets as $index => $target) {
            $variable = $this->statements->activation->variable($target->value) ?? throw ProgramError::UndeclaredVariable->error($target->value);
            $variable->assign($row[$index], $cursor->domains[$index], $context);
        }
    }
}
