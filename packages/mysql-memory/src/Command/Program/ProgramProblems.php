<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\ProgramErrors;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Problem\Errors;
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
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
use SqlSemantics\Platform\MySql\Statement\View\AlterView;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

/**
 * Raises the problems the server finds in a statement that stores a program, while it parses the body.
 *
 * A DEFINER account with a user name longer than 32 characters is refused first, for views too;
 * the tables read by a view are opened before its name is looked up; columns and functions follow.
 * The schedule of an event may read no table and call no stored function (verified on a live
 * 8.4 server).
 * The server parses the body of a routine, trigger or event when it stores it, but resolves its
 * tables, columns and stored functions only when it runs it. So only the problems of the body
 * itself are errors then: the rules of stored programs, a wrong call of a native function, and
 * in a trigger a column of the NEW or OLD row its table lacks; the rules come first. A
 * function without RETURN is named with its database.
 * Modern SHOW TRIGGERS looks up its database before it reads its condition.
 * MySQL 5.6 and 5.7 defer that lookup until rows are read.
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
     * Raises the first problem of a statement that stores a program, and answers whether the statement stores one.
     *
     * @throws SqlError When the body has a problem the server finds while parsing it
     */
    public function raise(MySqlStatement $operation, Session $session, Problems $problems, bool $prepared = false): bool
    {
        $statement = $operation;
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\View\DropView || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView) {
            return true;
        }
        if ($statement instanceof ShowTriggers) {
            $this->triggers($statement, $session, $prepared);

            return false;
        }
        $this->definer($statement, $session->settings()->release());
        if ($statement instanceof CreateView || $statement instanceof AlterView) {
            $this->view($statement, $operation, $session, $problems);
        }
        if (!self::stores($statement)) {
            return false;
        }
        if ($statement instanceof CreateEvent || $statement instanceof AlterEvent) {
            (new \MySqlMemory\Session\Access\EventSchedule())->statement($statement, $session);
            $this->schedule($statement, $operation);
        }
        $this->body($statement, $operation, $session, $problems);

        return true;
    }

    /**
     * Requires a database name and, on modern releases, its existence before the condition is read.
     *
     * @throws SqlError When the database does not exist (ER_BAD_DB_ERROR)
     */
    public function triggers(ShowTriggers $statement, Session $session, bool $prepared = false): void
    {
        $database = ProgramSource::database($statement->database, $session);
        if (!$prepared && !$session->settings()->legacy() && $session->instance->dictionary->schema($database) === null) {
            throw QueryError::BadDatabase->error($database);
        }
    }

    /**
     * Refuses the DEFINER account of a view or a stored program when a name of it is too long.
     *
     * @throws SqlError When a name of the account is too long
     */
    public function definer(\SqlSemantics\Statement\Statement $statement, \SqlSemantics\Contract\GrammarRelease $release = \SqlSemantics\Contract\GrammarRelease::MySql847): void
    {
        $definer = match (true) {
            $statement instanceof CreateView, $statement instanceof AlterView => $statement->definition->definer,
            $statement instanceof CreateProcedure, $statement instanceof CreateFunction, $statement instanceof CreateTrigger, $statement instanceof CreateEvent, $statement instanceof AlterEvent => $statement->definer,
            default => null,
        };
        if ($definer instanceof AccountName) {
            (new \MySqlMemory\Command\Account\Names($release))->check([$definer]);
        }
    }

    /**
     * Checks view query syntax and referenced tables before the target view, leaving column and function resolution until after its database and name exist.
     *
     * @throws SqlError When the query has such a problem
     */
    public function view(CreateView|AlterView $statement, MySqlStatement $operation, Session $session, Problems $problems): void
    {
        $name = $statement->definition->name;
        $named = static fn (Diagnostic $diagnostic): bool => ($diagnostic instanceof \SqlSemantics\Statement\Reference\Table\MissingTable && $diagnostic->name->name->value === $name->name->value && $diagnostic->name->schema?->value === $name->schema?->value)
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\WrongRelationKind;
        $others = array_values(array_filter($operation->statementInformation()->diagnostics, static fn (Diagnostic $diagnostic): bool => !$named($diagnostic) && !$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\ViewColumnCount
            && !$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\DuplicateColumn && !$diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Table\Problem\IncorrectColumnName));
        ProgramSource::database($name->schema, $session);
        (new \MySqlMemory\Session\Problem\Reading())->read($operation, $session);
        $problems->paths($operation, $session, $others);
        $problems->opened($operation, $session, $others);
        $database = ProgramSource::database($name->schema, $session);
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        if ($statement instanceof AlterView) {
            \MySqlMemory\Command\View\ViewCommand::checkName($statement, $schema, $database, $name->name->value);
        } else {
            ProgramSource::definer($statement->definition->definer, $session, diagnostics: $session->diagnostics);
        }
    }

    /**
     * Refuses the schedule of an event that reads a table or calls a stored function.
     *
     * @throws SqlError When the schedule reads a table or calls a stored function (ER_NOT_SUPPORTED_YET)
     */
    public function schedule(CreateEvent|AlterEvent $statement, MySqlStatement $operation): void
    {
        $schedule = $statement->schedule;
        if ($schedule === null) {
            return;
        }
        $walker = new Walker();
        foreach ((new \SqlSemantics\Platform\MySql\Rules\Routine\Event\Dependencies())->sources($schedule) as $source) {
            if (!$source instanceof FunctionCall || !$source->native($operation->profile()->grammar)) {
                throw StatementError::NotSupportedYet->error(\SqlSemantics\Platform\MySql\Rules\Routine\Event\Dependencies::feature($operation->profile()->grammar));
            }
        }
        foreach ($walker->find($schedule, \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast::class) as $cast) {
            if ($cast->array) {
                throw StatementError::NotSupportedYet->error(\SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast::ARRAY_OUTSIDE_INDEX);
            }
        }
    }

    /**
     * Raises the first problem the server finds while it parses the body of a stored program, the rules of stored programs first.
     *
     * @throws SqlError When the body has such a problem
     */
    public function body(\SqlSemantics\Statement\Statement $statement, MySqlStatement $operation, Session $session, Problems $problems): void
    {
        $diagnostics = $operation->statementInformation()->diagnostics;
        usort($diagnostics, static fn (Diagnostic $left, Diagnostic $right): int => ($left instanceof ProgramProblem ? 0 : 1) <=> ($right instanceof ProgramProblem ? 0 : 1));
        foreach ($diagnostics as $diagnostic) {
            if (!$this->parsed($diagnostic, $statement instanceof CreateTrigger)) {
                continue;
            }
            if ($diagnostic instanceof ProgramProblem && $diagnostic->rule === ProgramRule::MissingReturn && $statement instanceof CreateFunction) {
                $database = $statement->name->schema->value ?? $session->variables->database;

                throw new SqlError(ProgramError::MissingReturn, ProgramError::MissingReturn->message($database . '.' . $statement->name->name->value));
            }
            if ($diagnostic instanceof MissingColumn) {
                $row = strtoupper((string) $diagnostic->qualifier?->name->value);
                $event = $statement instanceof CreateTrigger ? $statement->event->value : '';
                if (($row === 'NEW' && $event === 'DELETE') || ($row === 'OLD' && $event === 'INSERT')) {
                    throw ProgramError::TriggerRowMissing->error($row, 'on ' . $event);
                }

                throw QueryError::BadField->error($diagnostic->name->value, $row);
            }

            throw $diagnostic instanceof ProgramProblem ? (new ProgramErrors())->error($diagnostic) : (new Errors())->error($diagnostic, $session, 'field list', $statement);
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

        return $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnstructuredVariable || $diagnostic instanceof ProgramProblem || $diagnostic instanceof WrongArgumentCount || $diagnostic instanceof NamedArgument || $diagnostic instanceof ReservedFunction
            || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation || $diagnostic instanceof \SqlSemantics\Platform\MySql\Statement\Query\Problem\UndeclaredVariable;
    }
}
