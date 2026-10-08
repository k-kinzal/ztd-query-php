<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\ProgramErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

/**
 * Raises the problems the server finds in a statement that stores a program, while it parses the body.
 *
 * A DEFINER account with a user name longer than 32 characters is refused first, for views too;
 * the query of a view is resolved before its name is looked up.
 * The schedule of an event may read no table and call no stored function (verified on a live
 * 8.4 server).
 * The server parses the body of a routine, trigger or event when it stores it, but resolves its
 * tables, columns and stored functions only when it runs it. So only the problems of the body
 * itself are errors then: the rules of stored programs, a wrong call of a native function, and
 * in a trigger a column of the NEW or OLD row its table lacks; the rules come first. A
 * function without RETURN is named with its database.
 * SHOW TRIGGERS looks up its database before it reads its condition.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/stored-program-restrictions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-event.html.
 *
 * @visibility MySqlMemory
 */
final class ProgramProblems
{
    /**
     * Tells whether a statement stores a program body.
     */
    public static function stores(\SqlSemantics\Statement\Statement $statement): bool
    {
        return $statement instanceof CreateProcedure || $statement instanceof CreateFunction || $statement instanceof CreateTrigger || $statement instanceof CreateEvent || $statement instanceof AlterEvent;
    }

    /**
     * Tells whether a diagnostic is an undeclared variable a routine declares as a parameter, as a LIMIT of its body may name one.
     */
    public static function parameter(\SqlSemantics\Statement\Statement $statement, Diagnostic $diagnostic): bool
    {
        if (!$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable || (!$statement instanceof CreateProcedure && !$statement instanceof CreateFunction)) {
            return false;
        }
        foreach ($statement->parameters->parameters as $parameter) {
            if (strcasecmp($parameter->name->value, $diagnostic->name->value) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Raises the first problem of a statement that stores a program, and answers whether the statement stores one.
     *
     * @throws SqlError When the body has a problem the server finds while parsing it
     */
    public function raise(Operation $operation, Session $session, Problems $problems): bool
    {
        $statement = $operation->statement;
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\View\DropView || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView) {
            return true;
        }
        if ($statement instanceof ShowTriggers) {
            $database = ProgramSource::database($statement->database, $session);
            if ($session->instance->dictionary->schema($database) === null) {
                throw ErrorCode::BadDatabase->error($database);
            }

            return false;
        }
        $definer = match (true) {
            $statement instanceof CreateView, $statement instanceof AlterView => $statement->definition->definer,
            $statement instanceof CreateProcedure, $statement instanceof CreateFunction, $statement instanceof CreateTrigger, $statement instanceof CreateEvent, $statement instanceof AlterEvent => $statement->definer,
            default => null,
        };
        if ($definer instanceof AccountName) {
            $this->account($definer);
        }
        if ($statement instanceof CreateView || $statement instanceof AlterView) {
            $name = $statement->definition->name;
            $named = static fn (Diagnostic $diagnostic): bool => ($diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable && $diagnostic->name->name->value === $name->name->value && $diagnostic->name->schema?->value === $name->schema?->value)
                || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;
            $others = array_values(array_filter($operation->facts->diagnostics, static fn (Diagnostic $diagnostic): bool => !$named($diagnostic) && !$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount
                && !$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn && !$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName));
            $walker = new Walker();
            foreach ([\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable::class, \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable::class, \SqlSemantics\Platform\MySql\Statement\Literal\Parameter::class] as $class) {
                if ($walker->find($statement->definition->query, $class) !== []) {
                    throw ErrorCode::ViewSelectVariable->error();
                }
            }
            if ($others !== [] && count($others) !== count($operation->facts->diagnostics) && array_filter($operation->facts->diagnostics, $named) !== []) {
                $query = ProgramSource::of($session)->text('query_expression_with_opt_locking_clauses');
                $problems->raise($session->analyze($query), $session);

                throw $problems->error($others[0], $session, 'field list', $statement);
            }
        }
        if (!self::stores($statement)) {
            return false;
        }
        $schedule = $statement instanceof CreateEvent || $statement instanceof AlterEvent ? $statement->schedule : null;
        if ($schedule !== null) {
            $walker = new Walker();
            $calls = array_filter($walker->find($schedule, FunctionCall::class), static fn (FunctionCall $call): bool => Problems::undeclared($call, $operation));
            if ($walker->find($schedule, \SqlSemantics\Platform\MySql\Statement\Relation\TableReference::class) !== [] || $walker->find($schedule, \SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable::class) !== [] || $calls !== []) {
                throw ErrorCode::NotSupportedYet->error('Usage of subqueries or stored function calls as part of this statement');
            }
        }
        $diagnostics = $operation->facts->diagnostics;
        usort($diagnostics, static fn (Diagnostic $left, Diagnostic $right): int => ($left instanceof ProgramProblem ? 0 : 1) <=> ($right instanceof ProgramProblem ? 0 : 1));
        foreach ($diagnostics as $diagnostic) {
            if (!$this->parsed($diagnostic, $statement instanceof CreateTrigger) || self::parameter($statement, $diagnostic)) {
                continue;
            }
            if ($diagnostic instanceof ProgramProblem && $diagnostic->rule === ProgramRule::MissingReturn && $statement instanceof CreateFunction) {
                $database = $statement->name->schema->value ?? $session->variables->database;

                throw new SqlError(ErrorCode::MissingReturn, ErrorCode::MissingReturn->message($database . '.' . $statement->name->name->value));
            }
            if ($diagnostic instanceof MissingColumn) {
                $row = strtoupper((string) $diagnostic->qualifier?->name->value);
                $event = $statement instanceof CreateTrigger ? $statement->event->value : '';
                if (($row === 'NEW' && $event === 'DELETE') || ($row === 'OLD' && $event === 'INSERT')) {
                    throw ErrorCode::TriggerRowMissing->error($row, 'on ' . $event);
                }

                throw ErrorCode::BadField->error($diagnostic->name->value, $row);
            }

            throw $diagnostic instanceof ProgramProblem ? (new ProgramErrors())->error($diagnostic) : $problems->error($diagnostic, $session, 'field list', $statement);
        }

        return true;
    }

    /**
     * Refuses a DEFINER account whose user name is longer than 32 characters or whose host name is longer than 255 (ER_WRONG_STRING_LENGTH).
     *
     * @throws SqlError When a name is too long
     */
    public function account(AccountName $account): void
    {
        if (mb_strlen($account->user->value) > 32) {
            throw ErrorCode::WrongStringLength->error($account->user->value, 'user name', 32);
        }
        if ($account->host !== null && mb_strlen($account->host->value) > 255) {
            throw ErrorCode::WrongStringLength->error($account->host->value, 'host name', 255);
        }
    }

    /**
     * Tells whether the server finds a problem while it parses a program body: one of its rules, a wrong native call, or an unknown column of the row of a trigger.
     */
    public function parsed(Diagnostic $diagnostic, bool $trigger): bool
    {
        if ($diagnostic instanceof MissingColumn) {
            $qualifier = strtoupper($diagnostic->qualifier->name->value ?? '');

            return $trigger && $diagnostic->qualifier?->schema === null && ($qualifier === 'NEW' || $qualifier === 'OLD');
        }

        return $diagnostic instanceof ProgramProblem || $diagnostic instanceof WrongArgumentCount || $diagnostic instanceof NamedArgument || $diagnostic instanceof ReservedFunction
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
    }
}
