<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Program\Invocation;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use Override;
use WeakReference;

/**
 * A call of a stored function: the function runs for each evaluation, with the values of the arguments then.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility MySqlMemory
 */
final class StoredCall implements Evaluable
{
    /**
     * @param Routine $routine The function
     * @param list<Evaluable> $arguments The arguments
     * @param WeakReference<Session> $session The session that runs the function, held weakly
     * @param string $name The name of the call, which an error converting the value it returns names
     */
    public function __construct(public readonly Routine $routine, public readonly array $arguments, public readonly WeakReference $session, public readonly string $name)
    {
    }

    /**
     * Answers the type the function returns.
     */
    #[Override]
    public function domain(): Domain
    {
        return $this->routine->returned();
    }

    /**
     * Runs the function and answers the value it returns.
     *
     * @throws \MySqlMemory\Error\SqlError When the function fails
     */
    #[Override]
    public function evaluate(Frame $frame): int|float|string|null
    {
        $values = [];
        foreach ($this->arguments as $argument) {
            $values[] = [$argument->evaluate($frame), $argument->domain()];
        }

        $session = $this->session->get() ?? throw \MySqlMemory\Error\Family\StatementError::NotSupportedYet->error('a stored function of a closed session');

        return (new Invocation($session))->function($this->routine, $values, $this->name);
    }
}
