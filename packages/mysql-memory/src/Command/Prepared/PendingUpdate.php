<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate;

/**
 * Keeps a prepared UPDATE with its complete deferred assignment values.
 *
 * @visibility MySqlMemory
 */
final class PendingUpdate implements PendingInstruction
{
    /**
     * @param DeferredUpdate $request The complete retained write
     * @param string $database The database selected during preparation
     */
    public function __construct(public readonly DeferredUpdate $request, public readonly string $database)
    {
    }

    /**
     * Reports legacy untyped marker slots in semantic occurrence order.
     */
    public function parameters(): ParameterBindings
    {
        $markers = (new \MySqlMemory\Evaluation\Compile\Walker())->find($this->request, \SqlSemantics\Platform\MySql\Statement\Literal\Parameter::class);
        return new ParameterBindings(array_map(static fn ($marker): array => [\MySqlMemory\Typing\Domain::string(0, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary()), false], $markers));
    }

    /**
     * Reaches the same write under its original selected database.
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
            (new \MySqlMemory\Program\Write\UpdateUse())->raise($this->request, $session, parameters: $parameters);
        } catch (SqlError $error) {
            throw (new \MySqlMemory\Session\Problem\Precision())->legacy($error, $session->settings()->release());
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
