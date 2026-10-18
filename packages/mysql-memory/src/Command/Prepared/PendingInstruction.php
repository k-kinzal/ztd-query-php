<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;

/**
 * A prepared semantic instruction whose retained condition is reached on execution.
 *
 * @visibility MySqlMemory
 */
interface PendingInstruction
{
    /**
     * Returns the protocol parameter domains retained during preparation.
     */
    public function parameters(): ParameterBindings;

    /**
     * Executes the typed instruction under its original preparation context.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and domains
     */
    public function execute(Session $session, string $label, array $parameters): Reply;

    /**
     * Executes through the protocol diagnostic boundary.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and domains
     * @return list<Reply|SqlError>
     */
    public function run(Session $session, string $label, array $parameters): array;
}
