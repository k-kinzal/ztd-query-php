<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile\Family;

use MySqlMemory\Error\ProgramError;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\StatementError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Printer;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Library;
use MySqlMemory\Evaluation\Leaf\Clock;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Statement\Scalar;

/**
 * Compiles calls of built-in functions, written by name or as keywords.
 *
 * A function the emulator does not evaluate is refused with ER_NOT_SUPPORTED_YET; a stored
 * function, which no statement can create here, does not exist (ER_SP_DOES_NOT_EXIST). ISNULL()
 * is the test IS NULL.
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
            throw ProgramError::RoutineMissing->error('FUNCTION', $call->schema->value . '.' . $call->name->value);
        }
        $arguments = array_map(static fn ($argument): Scalar => $argument->expression, $call->arguments);

        return $this->named($call->name->value, $arguments, $scope, $call);
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
        $compiled = array_map(fn (Scalar $argument): Evaluable => $this->compiler->compile($argument, $scope), $arguments);

        return new Call($routine, $compiled, $this->compiler->domain($node), (new Printer($this->compiler->facts, $this->compiler->settings->database))->expression($node));
    }

    /**
     * Compiles NOW(), CURDATE(), CURTIME() and the UTC clocks.
     */
    public function clock(ClockCall $call, Scope $scope): Evaluable
    {
        return new Clock($call->clock, $this->compiler->domain($call));
    }
}
