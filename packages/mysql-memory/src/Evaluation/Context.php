<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use WeakMap;

/**
 * What one statement is evaluated in: the session's modes and variables, the clock of the statement, and its diagnostics area.
 *
 * Every NOW() of a statement reads the same instant, the instant the statement started. A value
 * the server computes once for the statement, such as a constant operand a comparison converts,
 * is kept here the first time it is computed.
 *
 * @visibility MySqlMemory
 */
final class Context
{
    /**
     * The values computed once for the statement, by what computes them.
     *
     * @var WeakMap<object, list<int|float|string|null>>
     */
    public readonly WeakMap $kept;

    /**
     * @param SqlModes $modes The sql_mode of the session
     * @param Diagnostics $diagnostics Where warnings of the statement are recorded
     * @param Variables $variables The user and system variables the statement reads and assigns
     * @param float $started The Unix time the statement started, with microseconds
     * @param bool $strict Whether a write treats invalid data as an error rather than a warning
     */
    public function __construct(
        public readonly SqlModes $modes,
        public readonly Diagnostics $diagnostics,
        public readonly Variables $variables,
        public readonly float $started,
        public bool $strict = false,
    ) {
        $this->kept = new WeakMap();
    }

    /**
     * Records a warning, or raises it as an error when the statement writes data under a strict mode.
     *
     * @throws SqlError When the warning is an error in this statement
     */
    public function warn(ErrorCode $code, string|int ...$arguments): void
    {
        if ($this->strict) {
            throw $code->error(...$arguments);
        }
        $this->diagnostics->warning($code, $code->message(...$arguments));
    }

    /**
     * Records a warning whose message is not the format of its code, or raises it as an error when the statement writes data under a strict mode.
     *
     * @throws SqlError When the warning is an error in this statement
     */
    public function warnMessage(ErrorCode $code, string $message): void
    {
        if ($this->strict) {
            throw new SqlError($code, $message);
        }
        $this->diagnostics->warning($code, $message);
    }

    /**
     * Records a warning; a statement that writes data under a strict mode raises every warning as an error.
     *
     * @throws SqlError When the warning is an error in this statement
     */
    public function warning(ErrorCode $code, string|int ...$arguments): void
    {
        $this->warn($code, ...$arguments);
    }

    /**
     * Records a note: a condition of the Note level, which is never an error.
     */
    public function note(ErrorCode $code, string|int ...$arguments): void
    {
        $this->diagnostics->note($code, $code->message(...$arguments));
    }
}
