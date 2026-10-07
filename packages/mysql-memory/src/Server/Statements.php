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
     * @var array<int, array{string, int, list<int>, array<int, string>}> The text, parameter count, parameter types and long data of each statement, by id
     */
    private array $prepared = [];

    private int $next = 1;

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
        try {
            $session->split($sql);
            $operation = $session->analyze($sql, true);
            (new \MySqlMemory\Session\Problems())->raise($operation, $session);
            $tokens = $session->semantics()->parser()->tokenize($sql);
            $parameters = count(array_filter($tokens, static fn ($token): bool => $token->text === '?'));
            $columns = $this->columns($operation, $session, $parameters);
        } catch (SqlError $error) {
            return $this->client->send($this->client->messages->error($error->getCode(), $error->sqlState(), $error->getMessage()));
        }
        $id = $this->next++;
        $this->prepared[$id] = [$sql, $parameters, [], []];
        $this->client->packet((new PayloadWriter())->integer(0, 1)->integer($id, 4)->integer(count($columns), 2)->integer($parameters, 2)->integer(0, 1)->integer(0, 2)->payload());
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
    public function columns(\SqlSemantics\Statement\Operation $operation, \MySqlMemory\Session\Session $session, int $parameters): array
    {
        if (!$operation->statement instanceof \SqlSemantics\Statement\Query) {
            return [];
        }
        $context = new \MySqlMemory\Evaluation\Context($session->modes(), new \MySqlMemory\Session\Diagnostics(), $session->variables, microtime(true));
        $connection = new \MySqlMemory\Evaluation\Compile\Connection($session->variables, $context, $session->user, $session->host, $session->id, []);
        $planner = new \MySqlMemory\Plan\Planner($operation->statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);

        return (new \MySqlMemory\Command\Output())->columns($planner->query($operation->statement, null));
    }

    /**
     * Answers COM_STMT_EXECUTE: runs the statement with its parameters bound.
     *
     * @throws MalformedPacket When the command ends before a field
     */
    public function execute(PayloadReader $reader): bool
    {
        $id = $reader->integer(4);
        $reader->integer(1);
        $reader->integer(4);
        if (!isset($this->prepared[$id])) {
            return $this->client->send($this->client->messages->error(1243, 'HY000', \MySqlMemory\Error\ErrorCode::UnknownStatementHandler->message((string) $id, 'mysqld_stmt_execute')));
        }
        [$sql, $count, $types, $long] = $this->prepared[$id];
        $session = $this->client->session();
        $collation = Collation::named((string) $session->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');
        [$values, $types] = (new Binary())->parameters($reader, $count, $types, $collation);
        foreach ($long as $index => $data) {
            $values[$index] = (new Binary())->text($data, Field::Blob, $collation);
        }
        $this->prepared[$id] = [$sql, $count, $types, []];
        $answers = $session->run($sql, $values, true);
        foreach ($answers as $answer) {
            if ($answer instanceof SqlError) {
                return $this->client->send($this->client->messages->error($answer->getCode(), $answer->sqlState(), $answer->getMessage()));
            }
            $this->client->reply($answer, 0, $answer instanceof ResultSet);
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
