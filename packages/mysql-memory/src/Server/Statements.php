<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Protocol\Binary;
use MySqlMemory\Protocol\MalformedPacket;
use MySqlMemory\Protocol\PayloadReader;
use MySqlMemory\Protocol\PayloadWriter;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use WeakReference;

/**
 * The prepared statements of a connection: COM_STMT_PREPARE, COM_STMT_EXECUTE and the commands around them.
 *
 * A statement is prepared by parsing and resolving it; it is executed with the values bound to
 * its parameter markers, and its rows are sent in the binary protocol.
 * Source: https://dev.mysql.com/doc/dev/mysql-server/latest/page_protocol_command_phase_ps.html.
 *
 * @visibility MySqlMemory
 */
final class Statements
{
    /**
     * @var array<int, array{string, int, list<int>, array<int, string>, \MySqlMemory\Command\Prepared\ParameterBindings, \MySqlMemory\Command\Prepared\PreparedCache}> The request label, parameter count, wire types, long data, derived marker types and semantic definition of each statement, by id
     */
    private array $prepared = [];

    private int $next = 1;

    /**
     * @var array{int, int}|null Statement id and activity id of an unconsumed trigger load
     */
    private ?array $triggerLoad = null;

    /**
     * @param Client $client The connection
     */
    public function __construct(public readonly Client $client)
    {
    }

    /**
     * Answers one prepared statement command.
     *
     * @throws MalformedPacket When the command ends before a field
     */
    public function handle(int $command, PayloadReader $reader): bool
    {
        if (in_array($command, [0x18, 0x1A, 0x1C], true)) {
            \MySqlMemory\Session\State\StatementCounters::received($this->client->session(), $command !== 0x1A);
            \MySqlMemory\Session\State\StatementCounters::command($this->client->session(), match ($command) {
                0x18 => 'Com_stmt_send_long_data',
                0x1A => 'Com_stmt_reset',
                0x1C => 'Com_stmt_fetch',
            });
        }
        if ($command === 0x19) {
            $this->client->session()->activity->begin();
            \MySqlMemory\Session\State\StatementCounters::received($this->client->session(), false);
            \MySqlMemory\Session\State\StatementCounters::command($this->client->session(), 'Com_stmt_close');
        }
        return match ($command) {
            0x16 => $this->prepare($reader->rest()),
            0x17 => $this->execute($reader),
            0x18 => $this->longData($reader),
            0x19 => $this->close($reader->integer(4)),
            default => $this->client->send($this->client->messages->ok(0, 0, $this->client->status(), 0)),
        };
    }

    /**
     * Answers COM_STMT_PREPARE: the statement id, and the parameter and column definitions.
     */
    public function prepare(string $sql): bool
    {
        $session = $this->client->session();
        $session->activity->begin();
        $loads = $session->preparation->triggerResourceLoads;
        $this->triggerLoad = null;
        if ($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $session->diagnostics->retain();
        } else {
            $session->diagnostics->clear();
        }
        \MySqlMemory\Session\State\StatementCounters::received($session, false);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        try {
            $operation = (new \MySqlMemory\Session\Access\Preparation())->read($sql, $session);
            $bindings = $operation instanceof \MySqlMemory\Command\Prepared\PendingTable ? $operation->parameters() : new \MySqlMemory\Command\Prepared\ParameterBindings([]);
            $columns = [];
            if ($operation instanceof \SqlSemantics\Statement\AnalyzedStatement) {
                $node = $operation instanceof \SqlSemantics\Platform\MySql\Statement\Statement ? $operation : $operation;
                (new \MySqlMemory\Session\Execution($session))->area($operation, (new \MySqlMemory\Command\Dispatcher())->command($node));
                (new \MySqlMemory\Hint\Hints())->prepare($operation, $session);
                $bindings = \MySqlMemory\Command\Prepared\ParameterBindings::capture($operation);
                if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Statement) {
                    $columns = $this->columns($operation, $session, count($bindings->types));
                }
            }
            $parameters = count($bindings->types);
            $cache = \MySqlMemory\Command\Prepared\PreparedCache::capture($operation, $session);
            if ($cache->tables !== []) {
                $cache = new \MySqlMemory\Command\Prepared\PreparedCache($cache->definition, $cache->tables, (new \MySqlMemory\Session\Parse\Preparation\Alternatives())->read($sql, $session));
            }
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $session->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
            }
            foreach ($error->following as [$code, $message]) {
                $session->diagnostics->error($code, $message);
            }
            return $this->client->send($this->client->messages->error($error->getCode(), $error->sqlState(), $error->getMessage(), (string) $session->variables->read('character_set_results')));
        }
        $id = $this->next++;
        if ($session->preparation->triggerResourceLoads !== $loads) {
            $this->triggerLoad = [$id, (int) $session->variables->read('statement_id')];
        }
        $this->prepared[$id] = [$sql, $parameters, [], [], $bindings, $cache];
        return $this->metadata($id, $columns, $parameters);
    }

    /**
     * Sends a prepared handle and its parameter and result-column metadata.
     *
     * @param list<ResultColumn> $columns The resolved result fields
     */
    public function metadata(int $id, array $columns, int $parameters): bool
    {
        $session = $this->client->session();
        $this->client->packet((new PayloadWriter())->integer(0, 1)->integer($id, 4)->integer(count($columns), 2)->integer($parameters, 2)->integer(0, 1)->integer(min(65535, $session->diagnostics->raised()), 2)->payload());
        if ($parameters > 0) {
            for ($i = 0; $i < $parameters; $i++) {
                $this->client->packet($this->client->messages->column(new ResultColumn('?', Field::VarString, 0, 0, 128, 63)));
            }
            $this->client->packet($this->client->messages->eof(0, $this->client->status()));
        }
        if ($columns !== []) {
            foreach ($columns as $column) {
                $this->client->packet($this->client->messages->column($column));
            }
            $this->client->packet($this->client->messages->eof(0, $this->client->status()));
        }

        return true;
    }

    /**
     * Answers the column definitions a statement returns, planned without executing it.
     *
     * @return list<ResultColumn>
     */
    public function columns(\SqlSemantics\Platform\MySql\Statement\Statement $operation, \MySqlMemory\Session\Session $session, int $parameters): array
    {
        if (!$operation instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot) {
            return [];
        }
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), new \MySqlMemory\Session\Diagnostics(), $session->variables, $session->variables->instant());
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context, $session->user, $session->host, $session->id, [], $session->program, WeakReference::create($session));
        $planner = new \MySqlMemory\Plan\Planner($operation->query, $operation->statementInformation(), $session->settings(), $connection, $session->instance->dictionary, $session->temporaries, \MySqlMemory\Session\Statement\Catalog::snapshot($session));

        return (new \MySqlMemory\Command\Output())->columns($planner->query($operation->query, null));
    }

    /**
     * Answers COM_STMT_EXECUTE: runs the statement with its parameters bound.
     *
     * @throws MalformedPacket When the command ends before a field
     */
    public function execute(PayloadReader $reader): bool
    {
        \MySqlMemory\Session\State\StatementCounters::command($this->client->session(), 'Com_stmt_execute');
        $id = $reader->integer(4);
        $reader->integer(1);
        $reader->integer(4);
        if (!isset($this->prepared[$id])) {
            \MySqlMemory\Session\State\StatementCounters::received($this->client->session());
            return $this->client->send($this->client->messages->error(1243, 'HY000', \MySqlMemory\Error\Family\StatementError::UnknownStatementHandler->message((string) $id, 'mysqld_stmt_execute')));
        }
        [$sql, $count, $types, $long, $derived, $definition] = $this->prepared[$id];
        $session = $this->client->session();
        $session->preparation->replayedTriggerResource = $this->triggerLoad === [$id, (int) $session->variables->read('statement_id')];
        $this->triggerLoad = null;
        $collation = Collation::named((string) $session->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');
        [$values, $types] = (new Binary())->parameters($reader, $count, $types, $collation);
        foreach ($long as $index => $data) {
            $values[$index] = (new Binary())->text($data, Field::Blob, $collation);
        }
        $this->prepared[$id] = [$sql, $count, $types, [], $derived, $definition];
        try {
            $answers = $definition->run($session, $sql, $values, $derived);
        } finally {
            $session->preparation->replayedTriggerResource = false;
        }
        foreach ($answers as $index => $answer) {
            if ($answer instanceof SqlError) {
                return $this->client->send($this->client->messages->error($answer->getCode(), $answer->sqlState(), $answer->getMessage(), (string) $this->client->session()->variables->read('character_set_results')));
            }
            $this->client->reply($answer, $index < count($answers) - 1 ? 8 : 0, $answer instanceof ResultSet);
        }

        return true;
    }

    /**
     * Takes COM_STMT_SEND_LONG_DATA, which has no answer.
     *
     * @throws MalformedPacket When the command ends before a field
     */
    public function longData(PayloadReader $reader): bool
    {
        $id = $reader->integer(4);
        $parameter = $reader->integer(2);
        if (isset($this->prepared[$id])) {
            $this->prepared[$id][3][$parameter] = ($this->prepared[$id][3][$parameter] ?? '') . $reader->rest();
        }

        return true;
    }

    /**
     * Takes COM_STMT_CLOSE, which has no answer.
     */
    public function close(int $id): bool
    {
        unset($this->prepared[$id]);

        return true;
    }
}
