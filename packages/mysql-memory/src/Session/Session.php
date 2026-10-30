<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Instance;
use MySqlMemory\Result\Reply;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings as Resolution;
use SqlSemantics\Statement\Identifier\Name;
use WeakMap;

/**
 * A client session of the server: its current database, variables, diagnostics area and transaction.
 *
 * A statement is parsed with the grammar of the release under the session's sql_mode, resolved
 * by SQL Semantics against the tables of the server, and executed by the command of its kind.
 * Statements of one text run in order until one fails.
 *
 * @visibility MySqlMemory
 * @example Reading the warnings of a statement
 *     $session = (new \MySqlMemory\Instance())->connect();
 *     $session->query("SELECT 'x' + 1");
 *     $session->query('SHOW WARNINGS')[0]->rows // => [['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]]
 */
final class Session
{
    /**
     * The user variables and the session values of the system variables.
     */
    public readonly Variables $variables;

    /**
     * The warnings, notes and error of the last statement, as SHOW WARNINGS reads them.
     */
    public readonly Diagnostics $diagnostics;

    /**
     * @var array<int, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated> The deprecations MySQL 5.7 raises for the leading dots of the statement last parsed, by the offset of the dot
     */
    public array $dots = [];

    /**
     * Whether the statement runs a prepared statement, whose hints warned when it was prepared.
     */
    public bool $replayed = false;

    /**
     * Whether this connection must change its expired password before executing other statements.
     */
    public bool $passwordExpired = false;

    /**
     * The sequence and clock of the last client statement.
     */
    public readonly State\Activity $activity;

    /**
     * The database grant value retained independently of committed account state.
     */
    public readonly State\DatabaseGrants $databaseGrants;

    /**
     * The open transaction and the rows a failing statement restores.
     */
    public readonly Transaction $transaction;

    /**
     * @var list<State\TableLock> The explicit table handles, including their acquisition namespace
     */
    public array $locks = [];

    /**
     * @var array<string, \MySqlMemory\Command\Access\Handler> The tables HANDLER ... OPEN opened, by lowercase handler name
     */
    public array $handlers = [];

    /**
     * The text of the statement being executed, which a stored program keeps its body from.
     */
    public string $text = '';

    /**
     * The statements written after the statement being executed in the text the client sent, which the server quotes after it in a syntax error; once RELEASE ended the session, those it did not run.
     */
    public string $following = '';

    /**
     * Whether a COMMIT or ROLLBACK with RELEASE ended the session, as the server closes the connection then.
     */
    public bool $released = false;

    /**
     * Whether KILL QUERY interrupted the active statement.
     */
    public bool $interrupted = false;

    /**
     * Prepared statements and the parameter domains of the current execution.
     */
    public readonly State\Preparation $preparation;

    /**
     * The activation of the stored program the session runs a statement of, or null outside a program.
     */
    public ?\MySqlMemory\Program\Activation $program = null;

    /**
     * The statements the session runs, as its stored programs see them.
     */
    public readonly \MySqlMemory\Program\Running $running;

    /**
     * The temporary tables of the session.
     */
    public readonly \MySqlMemory\Dictionary\Temporaries $temporaries;

    /**
     * @var array<string, Semantics> The analyzers of each lexical mode, by mode
     */
    private array $semantics = [];

    /**
     * @param Instance $instance The server
     * @param int $id The connection id
     * @param string $user The user name
     * @param string $host The host connected from
     * @param string|null $database The database to use, or null for none
     * @param int|null $port The client's TCP source port, or null for a local session
     * @param \MySqlMemory\Command\Replication\Binlog\Stream $binlog The connection's independent runtime event stream
     *
     * @throws SqlError When the database does not exist
     */
    public function __construct(public readonly Instance $instance, public readonly int $id, public readonly string $user = 'root', public readonly string $host = 'localhost', ?string $database = null, public readonly ?int $port = null, public readonly \MySqlMemory\Command\Replication\Binlog\Stream $binlog = new \MySqlMemory\Command\Replication\Binlog\Stream())
    {
        $this->variables = new Variables($instance->catalog, $instance->globals, $instance);
        $this->preparation = new State\Preparation();
        $this->activity = new State\Activity($this->variables);
        $this->databaseGrants = new State\DatabaseGrants();
        $this->variables->connection = $id;
        $this->variables->account = $user . '@' . $host;
        $this->variables->definer = new \MySqlMemory\Account\Identity($user, '%');
        $this->passwordExpired = $instance->accounts->find(new \MySqlMemory\Account\Identity($user, '%'))->expired ?? false;
        $this->variables->roles = array_values($instance->accounts->defaults[(new \MySqlMemory\Account\Identity($user, '%'))->key()] ?? []);
        $this->diagnostics = new Diagnostics();
        $this->temporaries = new \MySqlMemory\Dictionary\Temporaries();
        $this->transaction = new Transaction($instance->dictionary, $id, $this->variables, $instance->transactions, $this->diagnostics, $this->temporaries);
        $instance->transactions->sessions[$id] = $this->transaction;
        $this->running = new \MySqlMemory\Program\Running();
        if ($database !== null) {
            $this->use($database);
        }
        $instance->registry->threads->connect($id);
    }

    /**
     * Ends the session, as the client disconnecting does: the user-level locks it holds are released, and its open transaction is rolled back, releasing its row locks.
     * Repeated closure is inert: a reset may already have reused this connection identity.
     * COMMIT/ROLLBACK RELEASE and KILL use the same transition; subsequent statements fail
     * with CR_SERVER_GONE_ERROR and the wire connection closes after the current reply.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html,
     * https://dev.mysql.com/doc/refman/8.4/en/innodb-autocommit-commit-rollback.html.
     */
    public function close(): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        $this->instance->registry->disconnect($this->id, $this->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651);
        $this->transaction->disconnect();
    }

    /**
     * Answers the error a statement fails with once RELEASE ended the session, the client error CR_SERVER_GONE_ERROR, or null while the session is open.
     */
    public function gone(): ?SqlError
    {
        return $this->released ? \MySqlMemory\Error\Family\TransactionError::ServerGone->error() : null;
    }

    /**
     * Ends the session when nothing refers to it any longer.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Runs the statements of a text and answers their replies; the first that fails raises its error.
     *
     * @return list<Reply>
     *
     * @throws SqlError When a statement fails
     */
    public function query(string $sql): array
    {
        $replies = [];
        foreach ($this->run($sql) as $reply) {
            if ($reply instanceof SqlError) {
                throw $reply;
            }
            $replies[] = $reply;
        }

        return $replies;
    }

    /**
     * Runs the statements of a text until one fails, and answers the reply or error of each run.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to parameter markers
     * @param bool $prepared Whether the text is a prepared statement, where parameter markers are allowed
     * @return list<Reply|SqlError>
     */
    public function run(string $sql, array $parameters = [], bool $prepared = false): array
    {
        $answers = [];
        foreach ((new Script\Replies($this))->run($sql, $parameters, $prepared) as [$answer]) {
            $answers[] = $answer;
        }

        return $answers;
    }

    /**
     * Splits a text into statements, or raises the syntax error of the first that does not parse.
     *
     * @return list<string>
     *
     * @throws SqlError When the text does not parse
     */
    public function split(string $sql): array
    {
        $sql = (new Script\Trailing())->input($sql);
        if (trim($sql) === '') {
            throw StatementError::EmptyQuery->error();
        }
        try {
            $statements = (new Script\Trailing())->statements($this->semantics()->split($sql), $this->semantics()->parser());
        } catch (AnalysisException $error) {
            throw (new Syntax(Charset::named((string) $this->variables->read('character_set_client'))))->error($error, $sql, $this->settings()->release(), parser: $this->semantics()->parser());
        }

        return $statements === [] ? [$sql] : $statements;
    }

    /**
     * Executes one statement.
     *
     * ROW_COUNT() then answers the rows the statement affected, or -1 when it answered rows or failed.
     * The warnings the server raises while it parses the statement are recorded first, in order,
     * with the error of each problem it finds while it parses, such as an unknown collation, up
     * to one that stops the parse; the first of those errors fails the statement.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_row-count.
     *
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters
     *
     * @throws SqlError When the statement fails
     */
    public function execute(string $statement, array $parameters = [], bool $prepared = false): Reply
    {
        if ($this->running->using === []) {
            $this->activity->begin();
            State\StatementCounters::received($this);
        }
        $this->text = $statement;
        $this->replayed = $prepared;
        try {
            $operation = $this->analyze($statement, $prepared, $parameters);
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $this->diagnostics->clear();
            }
            throw (new CacheOptions())->reported($error, $statement, $this);
        }

        return (new Execution($this))->statement($operation, $parameters);
    }

    /**
     * Parses and resolves one statement against the tables of the server.
     * A separator before the following statement still belongs to the request.
     * Prepared input is independent of an enclosing text script.
     *
     * @throws SqlError When the statement does not parse or does not resolve
     * @throws Parse\Definition\DeferredPreparation When prepared input retains an execution-time definition condition
     * @param list<array{int|float|string|\MySqlMemory\Evaluation\ExactDecimal|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers, in their order
     */
    public function analyze(string $statement, bool $prepared = false, array $parameters = []): MySqlStatement
    {
        if ($prepared || $this->following === '') {
            $statement = (new Script\Trailing())->input($statement);
        }
        $previousText = $this->text;
        $previousPrepared = $this->preparation->inputPrepared;
        $this->preparation->inputPrepared = $prepared;
        $this->text = $statement;
        try {
            $this->dots = [];
            $reader = new Parse\Reader();
            $tree = $reader->read($statement, $prepared, $this);
            $database = $this->variables->database;
            try {
                [$structure, $sources] = $reader->lower($tree, $this);
                $operation = (new Statement\Binding())->statement($this, $structure, $parameters, sources: $sources, prepared: $prepared);
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                throw $reader->operand($failure, $tree, $statement, $this);
            } catch (AnalysisException $error) {
                throw $reader->refusal($tree, $error, $statement, $database, $this);
            }
            try {
                (new Syntax(Charset::named((string) $this->variables->read('character_set_client'))))->debugOnly($tree, $statement);
            } catch (SqlError $error) {
                throw (new Parse\DebugRequest())->refusal($operation, $error, $this);
            }

            (new \MySqlMemory\Hint\Hints())->messages($operation, $statement, $this);

            if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent || $operation instanceof \SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent) {
                $this->preparation->programSources ??= new WeakMap();
                $this->preparation->programSources[$operation] = new \MySqlMemory\Command\Program\ProgramSource($statement, $tree);
            }

            return $operation;
        } finally {
            $this->text = $previousText;
            $this->preparation->inputPrepared = $previousPrepared;
        }
    }

    /**
     * Answers the session variables SQL Semantics resolves types with.
     *
     * @param list<int> $unboundParameters Positions with initial domains awaiting actual values
     *
     * @param array<int, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> $parameters The type of the value bound to each parameter marker, by the position of the marker
     */
    public function resolution(array $parameters = [], ?Resolution $literals = null, array $unboundParameters = []): Resolution
    {
        $schemas = [];
        foreach ($this->instance->dictionary->schemas as $schema) {
            $schemas[$schema->name] = Collation::named($schema->collation) ?? Collation::known('utf8mb4_0900_ai_ci');
        }
        $connection = Collation::named((string) $this->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');
        $server = Collation::named((string) $this->variables->read('collation_server'));

        $users = array_map(static fn (\MySqlMemory\Variable\UserEntry $variable) => new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariableEntry($variable->domain->resolved(), $variable->collation, $variable->binaryString), $this->variables->user);

        $client = $this->variables->read('character_set_client');
        $timestamps = $this->variables->read('explicit_defaults_for_timestamp');

        return new Resolution($literals->connection ?? $connection, (int) $this->variables->read('div_precision_increment'), $server, $schemas, $this->variables->count('group_concat_max_len', 1024), $users, $parameters, !$this->modes()->has('NO_UNSIGNED_SUBTRACTION'), $literals->client ?? (is_string($client) ? Charset::named($client) : null), program: $this->program?->rows() ?? [], functions: $this->instance->dictionary->functions(), blockEncryptionMode: (string) $this->variables->read('block_encryption_mode'), timeNames: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale::named((string) $this->variables->read('lc_time_names')), derivedMerge: !str_contains((string) $this->variables->read('optimizer_switch'), 'derived_merge=off'), strict: version_compare($this->instance->version, '8.0.0', '<') ? $this->modes()->strict() : ($literals->strict ?? $this->modes()->strict()), explicitDefaultsForTimestamp: $timestamps === null ? null : in_array($timestamps, ['ON', '1', 1], true), noZeroDate: $this->modes()->has('NO_ZERO_DATE'), noZeroInDate: $this->modes()->has('NO_ZERO_IN_DATE'), allowInvalidDates: $this->modes()->has('ALLOW_INVALID_DATES'), handlers: array_map(static fn (\MySqlMemory\Command\Access\Handler $handler): \SqlSemantics\Statement\Identifier\QualifiedName => new \SqlSemantics\Statement\Identifier\QualifiedName(new Name($handler->table), new Name($handler->schema)), $this->handlers), databaseCollation: Collation::named((string) $this->variables->read('collation_database')), replicaNetTimeout: (int) $this->variables->read(version_compare($this->instance->version, '8.0.0', '<') ? 'slave_net_timeout' : 'replica_net_timeout'), fullTextParsers: (new \MySqlMemory\System\Server\Plugins())->fullTextParsers(\SqlSemantics\Contract\GrammarRelease::tryFrom('mysql-' . $this->instance->version) ?? \SqlSemantics\Contract\GrammarRelease::MySql847), unboundParameters: $unboundParameters, realAsFloat: $this->modes()->has('REAL_AS_FLOAT'));
    }

    /**
     * Answers the analyzer of the session's lexical mode.
     */
    public function semantics(): Semantics
    {
        $mode = Mode::fromString((string) $this->variables->read('sql_mode'));
        $client = Charset::named((string) $this->variables->read('character_set_client'));
        $key = $mode->toString() . '|' . ($client->name ?? '');

        return $this->semantics[$key] ??= new Semantics(Dialect::MySql, 'mysql-' . $this->instance->version, $mode, ParameterStyle::Native, session: new Resolution(Collation::known('utf8mb4_0900_ai_ci'), client: $client));
    }

    /**
     * Answers the sql_mode of the session.
     */
    public function modes(): SqlModes
    {
        $release = \SqlSemantics\Contract\GrammarRelease::tryFrom('mysql-' . $this->instance->version) ?? \SqlSemantics\Contract\GrammarRelease::MySql847;

        return SqlModes::parse((string) $this->variables->read('sql_mode'), $release) ?? new SqlModes([], $release);
    }

    /**
     * Answers the settings statements are resolved under.
     */
    public function settings(): Settings
    {
        $resolution = $this->preparation->resolution ?? $this->resolution();

        return new Settings($resolution->connection, $this->modes(), (int) $this->variables->read('div_precision_increment'), $this->variables->database, $this->instance->version, $resolution);
    }

    /**
     * Makes a database the current one.
     *
     * @throws SqlError When the database does not exist
     */
    public function use(string $database): void
    {
        $schema = $this->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        $this->variables->database = $schema->name;
        $this->variables->databaseCollation(Collation::known($schema->collation));
    }
}
