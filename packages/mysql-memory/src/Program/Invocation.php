<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use Closure;
use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Node;

/**
 * Runs the body of a stored program in the session: in the database of the program, under the sql_mode it was created with.
 *
 * The current database and the sql_mode of the session are those of the program while it
 * runs, and come back when it ends. A stored function is called with the values of its
 * arguments, each stored into its parameter as into a column of its type; a function cannot
 * call itself, even through another program (ER_SP_NO_RECURSION). Its statements are part of
 * the statement that calls it, and the conditions its last statement left are added to the
 * diagnostics area of that statement, while ROW_COUNT() stays as it was. The value RETURN
 * answers is stored as into a column of the declared type, named after the call; a function
 * that ends without RETURN is ER_SP_NORETURNEND (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/stored-routines-syntax.html.
 *
 * @visibility MySqlMemory
 */
final class Invocation
{
    /**
     * @param Session $session The session the program runs in
     */
    public function __construct(public readonly Session $session)
    {
    }

    /**
     * Runs a body in an activation and answers the jump that ended it, if any.
     * Procedure exit copies stored conditions to the caller; counts of conditions beyond
     * max_error_count do not cross that boundary (verified on a live 8.0.44 server).
     *
     * @param string $database The database of the program
     * @param string $mode The sql_mode the program was created with
     *
     * @throws SqlError When the body raises an error no handler handles
     */
    public function run(Activation $activation, Node $body, string $database, string $mode): ?Jump
    {
        if ($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody) {
            throw (new \MySqlMemory\Error\ProgramErrors())->language($this->session->settings()->release());
        }
        $session = $this->session;
        $activation->announcesResults = ($activation->caller->announcesResults ?? false)
            || ($activation->kind === 'PROCEDURE' && (new \MySqlMemory\Program\Routine\Results())->announced($body));
        $variables = $session->variables;
        $saved = [$session->program, $variables->database, $variables->session];
        $session->program = $activation;
        $variables->database = $database;
        $collation = $session->instance->dictionary->schema($database)?->collation;
        if ($collation !== null) {
            $variables->databaseCollation(Collation::known($collation));
        }
        $variables->session['sql_mode'] = $mode;
        try {
            return (new Interpreter($session, $activation))->statement($body);
        } finally {
            if (!$activation->contained) {
                $session->diagnostics->dropped = 0;
                $session->diagnostics->droppedErrors = 0;
            }
            $session->program = $saved[0];
            $variables->database = $saved[1];
            foreach (['sql_mode', 'character_set_database', 'collation_database'] as $name) {
                if (array_key_exists($name, $saved[2])) {
                    $variables->session[$name] = $saved[2][$name];
                } else {
                    unset($variables->session[$name]);
                }
            }
        }
    }

    /**
     * Calls a stored function with the values of its arguments and answers the value it returns.
     *
     * @param list<array{int|float|string|null, Domain}> $arguments The value and type of each argument
     * @param string $name The name of the call, which an error converting the value it returns names
     *
     * @throws SqlError When the function calls itself, fails, ends without RETURN, or its value is refused
     */
    public function function(Routine $routine, array $arguments, string $name): int|float|string|null
    {
        $session = $this->session;
        $caller = $session->program;
        $qualified = $routine->schema . '.' . $routine->name;
        if ($caller !== null && $caller->depth('FUNCTION', $qualified) > 0) {
            throw ProgramError::NoRecursion->error();
        }
        $collation = Collation::named($routine->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci');
        $activation = new Activation('FUNCTION', $qualified, true, $collation, $caller);

        return $this->contained(function () use ($activation, $routine, $arguments, $name): int|float|string|null {
            $activation->scope[] = $this->parameters($routine, $arguments, $routine->mode);
            $jump = $this->run($activation, \MySqlMemory\Program\Routine\Body::of($routine)->body, $routine->schema, $routine->mode);
            if ($jump === null || $jump->flow !== Flow::Return) {
                throw ProgramError::FunctionWithoutReturn->error($routine->name);
            }

            return (new Store($this->context($routine->mode)))->value($jump->value, $jump->domain ?? Domain::null(), new ColumnDefinition($name, $routine->returned(), Fill::none()));
        });
    }

    /**
     * Runs a program with its own diagnostics area, retaining preceding conditions.
     * A new condition or SQL area reset discards stale conditions, even after handling;
     * notices raised while loading this invocation remain current and are preserved.
     * Functions and triggers also preserve ROW_COUNT(); procedures publish their final count.
     *
     * @template T
     *
     * @param Closure(): T $run
     * @param-immediately-invoked-callable $run
     * @param bool $preserveRowCount Whether the caller's row count survives the invocation
     * @param Activation|null $activation The reached SQL instructions for load-notice lifetime
     * @return T
     */
    public function contained(Closure $run, bool $preserveRowCount = true, ?Activation $activation = null): mixed
    {
        $session = $this->session;
        $diagnostics = $session->diagnostics;
        [$conditions, $signalled, $count] = [$diagnostics->conditions, $diagnostics->signalled, $session->variables->rowCount];
        [$stale, $revision] = [$diagnostics->stale, $diagnostics->revision];
        $diagnostics->clear(false);
        try {
            return $run();
        } finally {
            $own = [$diagnostics->conditions, $diagnostics->signalled];
            [$conditions, $signalled] = (new \MySqlMemory\Session\Access\StoredHints())->retained($conditions, $signalled, $activation->sqlStatements ?? 0);
            [$diagnostics->conditions, $diagnostics->signalled] = $stale && $diagnostics->revision !== $revision ? [[], []] : [$conditions, $signalled];
            if ($preserveRowCount) {
                $session->variables->rowCount = $count;
            }
            foreach ($own[0] as $position => [$level, $number, $message]) {
                if (isset($own[1][$position])) {
                    $diagnostics->signalled[count($diagnostics->conditions)] = $own[1][$position];
                }
                $diagnostics->conditions[] = [$level, $number, $message];
            }
        }
    }

    /**
     * Binds the values of the arguments of a routine to its parameters, each stored as into a column of its type.
     *
     * @param list<array{int|float|string|null, Domain}> $arguments The value and type of each argument; a missing one leaves its parameter NULL
     *
     * @throws SqlError When a value is refused
     */
    public function parameters(Routine $routine, array $arguments, string $mode): Row
    {
        $collation = Collation::named($routine->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci');
        $context = $this->context($mode);
        $row = new Row(\MySqlMemory\Program\Routine\Body::of($routine)->parameters, []);
        foreach (\MySqlMemory\Program\Routine\Body::of($routine)->parameters->parameters as $index => $parameter) {
            $variable = new Variable($parameter->name->value, VariableDomain::declared($parameter->type, $parameter->collation, $collation));
            if (isset($arguments[$index])) {
                $variable->assign($arguments[$index][0], $arguments[$index][1], $context);
            }
            $row->variables[] = $variable;
        }

        return $row;
    }

    /**
     * Answers a context that stores values strictly when an sql_mode is strict.
     */
    public function context(string $mode): Context
    {
        $session = $this->session;
        $modes = \MySqlMemory\Session\SqlModes::parse($mode, $session->modes()->release) ?? $session->modes();
        $context = new Context($modes, $session->diagnostics, $session->variables, microtime(true));
        $context->strict = $modes->strict();

        return $context;
    }
}
