<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;

/**
 * Keeps a prepared revocation's target and recipients without original source input.
 *
 * @visibility MySqlMemory
 */
final class PendingRevocation implements PendingInstruction
{
    /**
     * @param DeferredRevokePrivileges $request The complete retained instruction
     * @param string $database The database selected during preparation
     */
    public function __construct(public readonly DeferredRevokePrivileges $request, public readonly string $database)
    {
    }

    /**
     * Reports the empty binding of a privilege/recipient declaration.
     */
    public function parameters(): ParameterBindings
    {
        return new ParameterBindings([]);
    }

    /**
     * Reaches the same target condition under the original selected database.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The protocol bindings
     */
    public function execute(Session $session, string $label, array $parameters): Reply
    {
        if ($session->running->using === []) {
            $session->activity->begin();
            \MySqlMemory\Session\State\StatementCounters::received($session);
        }
        $database = $session->variables->database;
        $session->variables->database = $this->database;
        $session->text = $label;
        $session->diagnostics->clear();
        try {
            (new \MySqlMemory\Program\Account\RevokeUse())->raise($this->request, $session, false);
        } finally {
            $session->variables->database = $database;
        }
    }

    /**
     * Preserves the protocol's SQL error and warning reporting.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The protocol bindings
     * @return list<Reply|SqlError>
     */
    public function run(Session $session, string $label, array $parameters): array
    {
        $gone = $session->gone();
        if ($gone !== null) {
            return [$gone];
        }
        try {
            return [$this->execute($session, $label, $parameters)];
        } catch (SqlError $error) {
            return (new \MySqlMemory\Session\Execution($session))->failed($error);
        }
    }
}
