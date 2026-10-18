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
 * A prepared table template whose retained condition is checked on execution.
 * The typed refusal is retained without an ordinary semantic Statement or SQL.
 * Engine warnings belong to preparation; database lookup remains per execution.
 * A retained MERGE/query role rejects before table opening or metadata revalidation.
 * Observed through COM_STMT_PREPARE on MySQL 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class PendingTable
{
    /**
     * @param RefusedCreateTail|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $header The complete retained definition
     * @param ParameterBindings|null $bindings Prepared query parameters
     * @param string $database The default database selected at preparation
     */
    public function __construct(public readonly RefusedCreateTail|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable $header, public readonly string $database, public readonly ?ParameterBindings $bindings = null)
    {
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
        if ($this->header instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable) {
            if ((new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($this->header->elements)) {
                return (new \MySqlMemory\Program\Expression\GenerationUse())->execute($this->header, $session, $database);
            }
            throw \MySqlMemory\Error\Family\SchemaError::WrongObject->error($database, $this->header->name->name->value, 'BASE TABLE');
        }
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
