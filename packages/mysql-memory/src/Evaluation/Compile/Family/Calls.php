<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\Family\ProgramError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Printer;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Json\Predicate;
use MySqlMemory\Evaluation\Function\Library;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Statement\Scalar;
use WeakReference;

/**
 * Compiles calls of built-in functions, written by name or as keywords.
 *
 * A function the emulator does not evaluate is refused with ER_NOT_SUPPORTED_YET; a stored
 * function, which no statement can create here, does not exist (ER_SP_DOES_NOT_EXIST). ISNULL()
 * is the test IS NULL. A predicate given to a JSON function is marked, for its value becomes a
 * JSON boolean. A function that checks its arguments when the statement is resolved, as SHA2
 * does a known length, checks them once the arguments are compiled.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Calls
{
    /**
     * The functions whose string result reads as a number without warning from MySQL 8.0 on (verified on a live 8.4 server).
     */
    public const QUIET = ['GREATEST', 'LEAST'];

    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles a call written by name.
     *
     * @throws \MySqlMemory\Error\SqlError When the function is unknown or the arguments do not fit
     */
    public function function(FunctionCall $call, Scope $scope): Evaluable
    {
        $stored = $call->schema !== null || Library::instance()->find($call->name->value) === null ? $this->stored($call, $scope) : null;
        if ($stored !== null) {
            return $stored;
        }
        if ($call->schema !== null) {
            throw ProgramError::RoutineMissing->error('FUNCTION', $call->schema->value . '.' . $call->name->value);
        }
        $arguments = array_map(static fn ($argument): Scalar => $argument->expression, $call->arguments);

        return $this->named($call->name->value, $arguments, $scope, $call);
    }

    /**
     * Compiles a call of a stored function of the database the call names, else of the current one; answers null when there is no such function.
     *
     * The database is found without regard to letter case when no database has the name exactly (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the number of arguments is not that of the parameters (ER_SP_WRONG_NO_OF_ARGS)
     */
    public function stored(FunctionCall $call, Scope $scope): ?Evaluable
    {
        $session = $this->compiler->connection->session();
        $database = $call->schema->value ?? $this->compiler->settings->database;
        $schema = null;
        foreach ($session === null ? [] : $session->instance->dictionary->schemas as $candidate) {
            $schema = $candidate->name === $database || ($schema === null && strcasecmp($candidate->name, $database) === 0) ? $candidate : $schema;
        }
        $routine = $schema?->functions[strtolower($call->name->value)] ?? null;
        if ($routine === null || $session === null) {
            return null;
        }
        $count = count($routine->statement->parameters->parameters);
        if ($count !== count($call->arguments)) {
            throw ProgramError::RoutineArgumentCount->error('FUNCTION', $routine->schema . '.' . $routine->name, $count, count($call->arguments));
        }
        $arguments = array_map(fn ($argument): Evaluable => $this->compiler->compile($argument->expression, $scope), $call->arguments);

        return new \MySqlMemory\Evaluation\Function\StoredCall($routine, $arguments, WeakReference::create($session), (new Printer())->expression($call));
    }

    /**
     * Compiles a call of a function whose name is a keyword.
     *
     * GROUPING() is bound where a block groups WITH ROLLUP, so it is misused wherever it is compiled.
     *
     * @throws \MySqlMemory\Error\SqlError When the function is not evaluated here, or is GROUPING()
     */
    public function keyword(KeywordCall $call, Scope $scope): Evaluable
    {
        if ($call->function === KeywordFunction::Grouping) {
            throw QueryError::InvalidGroupFunctionUse->error();
        }

        return $this->named($call->function->value, $call->arguments, $scope, $call);
    }

    /**
     * Compiles a call of a named built-in function over argument nodes.
     *
     * @param list<Scalar> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the function is unknown or the arguments do not fit
     */
    public function named(string $name, array $arguments, Scope $scope, Scalar $node): Evaluable
    {
        $routine = Library::instance()->find($name);
        if ($routine === null) {
            throw StatementError::NotSupportedYet->error('function ' . strtoupper($name));
        }
        if (!$routine->accepts(count($arguments))) {
            throw QueryError::WrongParameterCountToNativeFunction->error(strtoupper($name));
        }
        if (strtoupper($name) === 'ISNULL') {
            return $this->compiler->operators->nullness($arguments[0], false, $scope, $node);
        }
        $json = str_starts_with(strtoupper($name), 'JSON_');
        $compiled = array_map(fn (Scalar $argument): Evaluable => $json && $this->compiler->jsons->boolean($argument) ? new Predicate($this->compiler->compile($argument, $scope)) : $this->compiler->compile($argument, $scope), $arguments);
        if ($routine->resolve !== null) {
            $compiled = ($routine->resolve)(new Frame($this->compiler->connection->context), $compiled, array_map(fn (Scalar $argument): bool => $this->compiler->constancy($argument) === Constancy::Resolved || ($routine->settled && $this->compiler->constancy($argument) === Constancy::Statement), $arguments));
        }

        return new Call($routine, $compiled, $this->compiler->domain($node)->withSource(strtolower($name)), (new Printer($this->compiler->facts, $this->compiler->settings->database))->expression($node));
    }

    /**
     * Compiles NOW(), CURDATE(), CURTIME() and the UTC clocks.
     */
    public function clock(ClockCall $call, Scope $scope): Evaluable
    {
        return new Clock($call->clock, $this->compiler->domain($call));
    }
}
