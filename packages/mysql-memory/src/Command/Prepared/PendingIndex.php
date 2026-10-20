<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use LogicException;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex;

/**
 * A prepared index instruction retaining its complete target and non-scalar key.
 *
 * @visibility MySqlMemory
 */
final class PendingIndex implements PendingInstruction
{
    /**
     * @param DeferredCreateIndex $definition The unchanged retained instruction
     * @param string $database The default database at preparation
     */
    public function __construct(public readonly DeferredCreateIndex $definition, public readonly string $database)
    {
    }

    /**
     * Returns the index instruction's empty protocol parameter list.
     */
    public function parameters(): ParameterBindings
    {
        return new ParameterBindings([]);
    }

    /**
     * Rebinds the same instruction in current catalog state before reporting its condition.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The protocol bindings
     * @throws SqlError When the target or retained key cannot be prepared
     * @throws LogicException When the retained key loses its required condition
     */
    public function execute(Session $session, string $label, array $parameters): never
    {
        $session->activity->begin();
        \MySqlMemory\Session\State\StatementCounters::received($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_create_index');
        $session->diagnostics->clear();
        $session->text = $label;
        (new \MySqlMemory\Program\Definition\IndexUse())->raise($this->definition, $session, false, $this->database);
    }

    /**
     * Reports an execution condition through the prepared protocol.
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
            $this->execute($session, $label, $parameters);
        } catch (SqlError $error) {
            return (new \MySqlMemory\Session\Execution($session))->failed($error);
        }
    }
}
