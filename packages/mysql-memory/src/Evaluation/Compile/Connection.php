<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Variables;
use WeakReference;

/**
 * What a statement reads of its connection when it is resolved: the account, the thread, the variables, the bound parameters, and the stored program it runs in.
 *
 * @visibility MySqlMemory
 */
final class Connection
{
    /**
     * @param Variables $variables The variables of the session
     * @param Context $context The evaluation context of the statement
     * @param string $user The user name the connection authenticated as
     * @param string $host The host the connection came from
     * @param int $id The connection id (CONNECTION_ID())
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers, in order
     * @param \MySqlMemory\Program\Activation|null $program The stored program whose variables the statement reads, or null outside a program
     * @param WeakReference<\MySqlMemory\Session\Session>|null $session The session that runs the statement, which runs the stored functions it calls, held weakly so that a session nothing else refers to closes; null where none can be called
     */
    public function __construct(
        public readonly Variables $variables,
        public readonly Context $context,
        public readonly string $user = 'root',
        public readonly string $host = 'localhost',
        public readonly int $id = 1,
        public readonly array $parameters = [],
        public readonly ?\MySqlMemory\Program\Activation $program = null,
        public readonly ?WeakReference $session = null,
    ) {
    }

    /**
     * Answers the session that runs the statement, or null where stored functions cannot be called.
     */
    public function session(): ?\MySqlMemory\Session\Session
    {
        return $this->session?->get();
    }
}
