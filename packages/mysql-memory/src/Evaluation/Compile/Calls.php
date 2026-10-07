<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Library;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles calls of built-in functions, written by name or as keywords.
 *
 * A function the emulator does not evaluate is refused with ER_NOT_SUPPORTED_YET; a stored
 * function, which no statement can create here, does not exist (ER_SP_DOES_NOT_EXIST).
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Calls
{
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
        if ($call->schema !== null) {
            throw ErrorCode::RoutineMissing->error('FUNCTION', $call->schema->value . '.' . $call->name->value);
        }
        $arguments = array_map(static fn ($argument): Scalar => $argument->expression, $call->arguments);

        return $this->named($call->name->value, $arguments, $scope, $call);
    }

    /**
     * Compiles a call of a function whose name is a keyword.
     *
     * @throws \MySqlMemory\Error\SqlError When the function is not evaluated here
     */
    public function keyword(KeywordCall $call, Scope $scope): Evaluable
    {
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
            throw ErrorCode::NotSupportedYet->error('function ' . strtoupper($name));
        }
        if (!$routine->accepts(count($arguments))) {
            throw ErrorCode::WrongParameterCountToNativeFunction->error(strtoupper($name));
        }
        $compiled = array_map(fn (Scalar $argument): Evaluable => $this->compiler->compile($argument, $scope), $arguments);

        return new Call($routine, $compiled, $this->compiler->domain($node));
    }

    /**
     * Compiles NOW(), CURDATE(), CURTIME() and the UTC clocks.
     */
    public function clock(ClockCall $call, Scope $scope): Evaluable
    {
        return new Clock($call->clock, $this->compiler->domain($call));
    }
}
