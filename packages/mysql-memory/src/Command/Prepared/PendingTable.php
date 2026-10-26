<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Prepared;

use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTail;

/**
 * A prepared, incomplete partition request whose retained scalar fails on execution.
 * Complete CREATE and ALTER requests use PreparedStatement.
 *
 * @visibility MySqlMemory
 */
final class PendingTable
{
    /**
     * @param RefusedCreateTail $header The header retained before a refused partition operand
     * @param string $database The default database selected at preparation
     */
    public function __construct(public readonly RefusedCreateTail $header, public readonly string $database)
    {
    }

    /**
     * Exposes no markers for the incomplete header retained before the refused tail.
     */
    public function parameters(): ParameterBindings
    {
        return new ParameterBindings([]);
    }

    /**
     * Checks the same retained definition under the current database catalog.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The protocol bindings
     * @throws SqlError When the destination or the retained partition condition is refused
     */
    public function execute(Session $session, string $label, array $parameters): Reply
    {
        $session->activity->begin();
        \MySqlMemory\Session\State\StatementCounters::received($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_create_table');
        $session->diagnostics->clear();
        $session->text = $label;
        $database = $this->header->name->schema->value ?? $this->database;
        if ($session->instance->dictionary->schema($database) === null) {
            throw QueryError::BadDatabase->error($database);
        }
        throw PartitionError::FunctionNotAllowed->error();
    }

    /**
     * Reports the execution condition through the prepared protocol.
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
