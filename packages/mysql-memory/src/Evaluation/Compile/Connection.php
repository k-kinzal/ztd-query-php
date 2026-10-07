<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Variables;

/**
 * What a statement reads of its connection when it is resolved: the account, the thread, the variables, and the bound parameters.
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
     */
    public function __construct(
        public readonly Variables $variables,
        public readonly Context $context,
        public readonly string $user = 'root',
        public readonly string $host = 'localhost',
        public readonly int $id = 1,
        public readonly array $parameters = [],
    ) {
    }
}
