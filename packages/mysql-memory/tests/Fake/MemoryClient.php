<?php

declare(strict_types=1);

namespace Tests\Fake;

use Closure;
use LogicException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Result\ResultColumn;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution as StatementExecution;
use MySqlMemory\Session\Session;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Scalar;
use Tests\Generator\Bindings;
use Tests\Generator\Embedding;

/**
 * Observes direct construction without submitting rendered SQL to memory.
 * Only SQL errors are outcomes; internal exceptions fail the test.
 * A direct SHOW WARNINGS reads diagnostics after saving the response,
 * including its effects on subsequent request counters and session state.
 */
final class MemoryClient
{
    /**
     * @return array{results: list<array<string, mixed>>, error?: array{int, string, string}, warnings: list<array{string, string, string}>|null, disconnected: bool, warningsError?: array{int, string, string}} All result sets, the terminal error and the separate condition-reading outcome
     * @param list<array{int|float|string|null, Domain}>|null $parameters Bindings selected before execution, or the local NULL fixture
     * @param bool $prepared Whether the selected transport prepares even without outer parameters
     */
    public function observe(Session $session, Statement|Scalar $node, ?array $parameters = null, bool $prepared = false): array
    {
        $parameters ??= (new Bindings())->nulls($node);
        try {
            $replies = [$this->runIn($session, $node, $parameters, $prepared)];
        } catch (SqlError $error) {
            $replies = (new StatementExecution($session))->failed($error);
        }
        $replies = $this->flatten($replies);
        $observation = $this->replies($replies, fn (ResultSet $result): array => (new ResultServer())->result($session->instance->version, $result, $prepared || $parameters !== []));
        if ($this->disrupted($replies)) {
            $session->close();
        }
        $observation['warnings'] = $session->released ? null : array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $observation['disconnected'] = $session->released;
        if (!$session->released) {
            try {
                $this->runIn($session, new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowWarnings());
            } catch (SqlError $error) {
                (new StatementExecution($session))->failed($error);
                $observation['warnings'] = null;
                $observation['warningsError'] = [$error->getCode(), $error->sqlState(), $error->getMessage()];
            }
        }

        return $observation;
    }

    /**
     * @param list<Reply|SqlError> $replies The replies, including results before an error
     * @param Closure(ResultSet): array{columns: list<array<string, int|string>>, rows: list<list<string|null>>}|null $interrupted Observes bytes that interrupt an outer result
     * @return array{results: list<array<string, mixed>>, error?: array{int, string, string}}
     * @throws LogicException When a reply has no observation rule
     */
    public function replies(array $replies, ?Closure $interrupted = null): array
    {
        $observation = ['results' => []];
        foreach ($this->flatten($replies) as $reply) {
            if ($reply instanceof SqlError) {
                $observation['error'] = [$reply->getCode(), $reply->sqlState(), $reply->getMessage()];
            } elseif ($reply instanceof Completion) {
                $observation['results'][] = ['affected' => $reply->affectedRows, 'lastInsertId' => (string) $reply->lastInsertId];
            } elseif ($reply instanceof ResultSet) {
                if ($reply->interruptions !== [] || $reply->binaryValues !== []) {
                    if ($interrupted === null) {
                        throw new LogicException('Binary floating values and nested emissions require transport observation.');
                    }
                    $observation['results'][] = $interrupted($reply);
                    if ($reply->interruptions !== [] || $reply->moreResults === false) {
                        break;
                    }
                    continue;
                }
                if ($reply->complete) {
                    $observation['results'][] = ['columns' => array_map($this->column(...), $reply->columns), 'rows' => array_map(fn (array $row): array => array_map($this->value(...), $row, $reply->columns), $reply->rows)];
                }
                if ($reply->moreResults === false) {
                    break;
                }
            } else {
                throw new LogicException('No direct observation for ' . $reply::class);
            }
        }

        return $observation;
    }

    /**
     * Flattens logical batches without discarding an unannounced completion.
     *
     * @param list<Reply|SqlError> $replies
     * @return list<Reply|SqlError>
     */
    public function flatten(array $replies): array
    {
        $flat = [];
        foreach ($replies as $reply) {
            array_push($flat, ...($reply instanceof Batch ? $this->flatten($reply->replies) : [$reply]));
        }

        return $flat;
    }

    /**
     * Mirrors the driver closing a response whose remaining packets were not announced.
     * An embedded result interrupts its caller's row stream; a procedure result can
     * instead finish normally but leave an unannounced final completion unread.
     *
     * @param list<Reply|SqlError> $replies Flattened replies
     */
    public function disrupted(array $replies): bool
    {
        foreach ($replies as $index => $reply) {
            if ($reply instanceof ResultSet && ($reply->interruptions !== [] || ($reply->moreResults === false && isset($replies[$index + 1])))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mirrors mysqli's BIT-to-unsigned-decimal conversion without changing stored row bytes.
     * Binary string fields retain their bytes, including NUL and high-bit octets.
     */
    public function value(?string $value, ResultColumn $column): ?string
    {
        if ($value === null || $column->type !== \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Bit) {
            return $value;
        }
        $number = '0';
        foreach (str_split($value) as $byte) {
            $number = bcadd(bcmul($number, '256', 0), (string) ord($byte), 0);
        }

        return $number;
    }

    /**
     * @return array<string, int|string> The metadata visible through mysqli
     */
    public function column(ResultColumn $column): array
    {
        return ['name' => $column->name, 'orgname' => $column->originalName, 'table' => $column->table, 'orgtable' => $column->originalTable, 'db' => $column->schema, 'charsetnr' => $column->charset, 'length' => min($column->length, 0xFFFFFFFF), 'type' => $column->type->value, 'flags' => $column->flags & 0xFFFF, 'decimals' => $column->decimals];
    }
    /**
     * Derives the structure against the session context and executes it directly.
     *
     * @param list<array{int|float|string|null, Domain}> $parameters Values and domains bound to the generated parameter occurrences
     * @throws SqlError When the database rejects the generated structure
     */
    public function run(Statement|Scalar|\SqlSemantics\Statement\Query|Operation $node, string $release = '8.4.7', array $parameters = [], ?string $mode = null): Reply
    {
        $session = (new Instance($release))->connect();
        if ($mode !== null) {
            $session->variables->session['sql_mode'] = $mode;
        }

        return $this->runIn($session, $node, $parameters, protocol: false);
    }

    /**
     * Executes directly constructed structures against shared session state.
     *
     * @param list<array{int|float|string|null, Domain}> $parameters The values bound to parameter occurrences
     * @param bool $prepared Whether to prepare the structure even with no outer parameters
     * @param bool $protocol Whether to include text or prepared protocol preparation in a native comparison
     * @throws SqlError When execution rejects the structure
     */
    public function runIn(Session $session, Statement|Scalar|\SqlSemantics\Statement\Query|Operation $node, array $parameters = [], bool $prepared = false, bool $protocol = true): Reply
    {
        $failure = $session->gone();
        if ($failure !== null) {
            throw $failure;
        }
        $prepared = $protocol && ($prepared || $parameters !== []);
        if ($session->running->using === []) {
            $session->activity->begin();
            \MySqlMemory\Session\State\StatementCounters::received($session, !$prepared);
        }
        $session->hinted = [];
        $session->dots = [];
        $operation = $node instanceof Operation ? $node : $this->operation($session, (new Embedding())->statement($node), $parameters);

        return $protocol
            ? $this->execute($session, $operation, $parameters, $prepared)
            : (new StatementExecution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
    }

    /**
     * Resolves an original statement in the session's current typing context.
     *
     * @param list<array{int|float|string|null, Domain}> $parameters Bound values and their domains
     */
    public function operation(Session $session, Statement $statement, array $parameters): Operation
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-' . $session->instance->version, Mode::fromString((string) $session->variables->read('sql_mode')), (new Bindings())->parameters($statement));
        return (new \MySqlMemory\Session\Statement\Binding())->operation($session, $statement, $parameters, $semantics->profile());
    }

    /**
     * Uses the shared typed preparation path when bindings require the native
     * prepared protocol, then executes and closes that one-shot statement.
     *
     * @param list<array{int|float|string|null, Domain}> $parameters Bound values and domains
     * @throws SqlError When preparation or execution rejects the operation
     */
    public function execute(Session $session, Operation $operation, array $parameters, bool $prepared = false): Reply
    {
        $execution = new StatementExecution($session);
        $command = (new Dispatcher())->command($operation->statement);
        if ($parameters === [] && !$prepared) {
            return $execution->perform($operation, $command);
        }
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_prepare');
        $execution->area($operation, $command);
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
        \MySqlMemory\Session\State\StatementCounters::received($session);
        \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_execute');
        $replayed = $session->replayed;
        $session->replayed = true;
        try {
            return $execution->perform($operation, $command, $parameters);
        } finally {
            $session->replayed = $replayed;
            \MySqlMemory\Session\State\StatementCounters::received($session, false);
            \MySqlMemory\Session\State\StatementCounters::command($session, 'Com_stmt_close');
        }
    }


}
