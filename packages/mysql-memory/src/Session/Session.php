<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Instance;
use MySqlMemory\Result\Reply;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings as Resolution;
use SqlSemantics\Statement\Operation;
use WeakReference;

/**
 * A client session of the server: its current database, variables, diagnostics area and transaction.
 *
 * A statement is parsed with the grammar of the release under the session's sql_mode, resolved
 * by SQL Semantics against the tables of the server, and executed by the command of its kind.
 * Statements of one text run in order until one fails.
 *
 * @visibility public
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
     * @var list<array{string, bool}> The warnings about the problems of the hint comments of the statement last parsed, in written order, each with whether its comment follows the first keyword of the statement
     */
    public array $hinted = [];

    /**
     * Whether the text of the statement last parsed holds a hint comment.
     */
    public bool $commented = false;

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
     * The open transaction and the rows a failing statement restores.
     */
    public readonly Transaction $transaction;

    /**
     * @var list<array{string, string, string, bool}> The tables LOCK TABLES locked: the database, the table, the name the statements use and whether the lock is a WRITE lock
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
     *
     * @throws SqlError When the database does not exist
     */
    public function __construct(public readonly Instance $instance, public readonly int $id, public readonly string $user = 'root', public readonly string $host = 'localhost', ?string $database = null, public readonly ?int $port = null)
    {
        $this->variables = new Variables($instance->catalog, $instance->globals, $instance);
        $this->preparation = new State\Preparation();
        $this->activity = new State\Activity($this->variables);
        $this->variables->connection = $id;
        $this->variables->account = $user . '@' . $host;
        $this->variables->definer = $user . '@%';
        $this->passwordExpired = $instance->accounts->find(new \MySqlMemory\Account\Identity($user, '%'))->expired ?? false;
        $this->variables->roles = array_values($instance->accounts->defaults[(new \MySqlMemory\Account\Identity($user, '%'))->key()] ?? []);
        $this->diagnostics = new Diagnostics();
        $this->temporaries = new \MySqlMemory\Dictionary\Temporaries();
        $this->transaction = new Transaction($instance->dictionary, $id, $this->variables, $instance->transactions, $this->diagnostics);
        $instance->transactions->sessions[$id] = $this->transaction;
        $session = WeakReference::create($this);
        $this->transaction->access->resumed = static function () use ($session): void {
            $resumed = $session->get();
            if ($resumed !== null) {
                $resumed->instance->dictionary->temporaries = $resumed->temporaries;
            }
        };
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
        $this->instance->registry->status->clear($this->id);
        $this->instance->registry->threads->disconnect($this->id);
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
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to parameter markers
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
        if (trim($sql) === '') {
            throw StatementError::EmptyQuery->error();
        }
        try {
            $statements = $this->semantics()->split($sql);
        } catch (AnalysisException $error) {
            throw (new Syntax())->error($error, $sql, $this->settings()->release());
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
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters
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
            $this->hinted = $prepared ? [] : $this->hinted;
            $command = (new Dispatcher())->command($operation->statement);
        } catch (SqlError $error) {
            if (!$error->recorded) {
                $this->diagnostics->clear();
            }
            throw (new CacheOptions())->reported($error, $statement, $this);
        }

        return (new Execution($this))->perform($operation, $command, $parameters);
    }

    /**
     * Parses and resolves one statement against the tables of the server.
     *
     * @throws SqlError When the statement does not parse or does not resolve
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound to the parameter markers, in their order
     */
    public function analyze(string $statement, bool $prepared = false, array $parameters = []): Operation
    {
        $semantics = $this->semantics();
        $this->instance->dictionary->temporaries = $this->temporaries;
        $this->dots = [];
        $reader = new Parse\Reader();
        $tree = $reader->read($statement, $prepared, $this);
        $database = $this->variables->database;
        \MySqlMemory\Plan\Views::refreshAll($this->instance->dictionary, $this->settings());
        $typing = (new \MySqlMemory\Hint\Hints())->typing($tree, $statement, $this);
        try {
            $operation = $semantics->analyze($tree, $semantics->context($this->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $this->resolution($reader->bound($tree, $parameters, $prepared, $this))));
        } catch (ImplementationGap $gap) {
            throw new SqlError(StatementError::NotSupportedYet, StatementError::NotSupportedYet->message($gap->getMessage()), $gap);
        } catch (AnalysisException $error) {
            throw $reader->refusal($tree, $error, $statement, $database, $this);
        } finally {
            $typing->restore($this);
        }
        (new Syntax())->internal($operation->statement, $statement);
        (new Syntax())->purged($tree, $operation->statement, $statement, $this->settings()->release());
        if ((new \MySqlMemory\Command\Condition\SignalProblems())->signals($tree)) {
            (new \MySqlMemory\Command\Condition\SignalProblems())->check($operation, $this);
        }

        return $operation;
    }

    /**
     * Answers the session variables SQL Semantics resolves types with.
     *
     * @param array<int, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> $parameters The type of the value bound to each parameter marker, by the position of the marker
     */
    public function resolution(array $parameters = []): Resolution
    {
        $schemas = [];
        foreach ($this->instance->dictionary->schemas as $schema) {
            $schemas[$schema->name] = Collation::named($schema->collation) ?? Collation::known('utf8mb4_0900_ai_ci');
        }
        $connection = Collation::named((string) $this->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');
        $server = Collation::named((string) $this->variables->read('collation_server'));

        $users = array_map(static fn (array $variable) => $variable[1]->resolved(), $this->variables->user);

        $client = $this->variables->read('character_set_client');

        return new Resolution($connection, (int) $this->variables->read('div_precision_increment'), $server, $schemas, $this->variables->count('group_concat_max_len', 1024), $users, $parameters, !$this->modes()->has('NO_UNSIGNED_SUBTRACTION'), is_string($client) ? Charset::named($client) : null, program: $this->program?->rows() ?? [], functions: $this->instance->dictionary->functions(), blockEncryptionMode: (string) $this->variables->read('block_encryption_mode'), timeNames: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale::named((string) $this->variables->read('lc_time_names')));
    }

    /**
     * Answers the analyzer of the session's lexical mode.
     */
    public function semantics(): Semantics
    {
        $mode = Mode::fromString((string) $this->variables->read('sql_mode'));
        $key = $mode->toString();

        return $this->semantics[$key] ??= new Semantics(Dialect::MySql, 'mysql-' . $this->instance->version, $mode, ParameterStyle::Native);
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
        $collation = Collation::named((string) $this->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');

        return new Settings($collation, $this->modes(), (int) $this->variables->read('div_precision_increment'), $this->variables->database, $this->instance->version, $this->resolution());
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
    }
}
