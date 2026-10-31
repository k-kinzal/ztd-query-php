<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;

/**
 * A prepared request the server accepts at PREPARE and refuses or resolves only when it executes.
 *
 * @visibility MySqlMemory
 */
interface Pending
{
    /**
     * Exposes the parameter markers of the retained request.
     */
    public function parameters(): ParameterBindings;

    /**
     * Executes the retained request under the current state.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters The protocol bindings
     * @throws SqlError When the retained condition is refused
     */
    public function execute(Session $session, string $label, array $parameters): Reply;
}
