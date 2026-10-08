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
use SqlParser\Lexer\SourceException;
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
     * @var array<string, array{string, int}> The text and parameter count of each statement PREPARE named, by lower-case name
     */
    public array $prepared = [];

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
     *
     * @throws SqlError When the database does not exist
     */
    public function __construct(public readonly Instance $instance, public readonly int $id, public readonly string $user = 'root', public readonly string $host = 'localhost', ?string $database = null)
    {
        $this->variables = new Variables($instance->catalog, $instance->globals, $instance);
        $this->variables->connection = $id;
        $this->variables->account = $user . '@' . $host;
        $this->variables->definer = $user . '@%';
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
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/locking-functions.html,
     * https://dev.mysql.com/doc/refman/8.4/en/innodb-autocommit-commit-rollback.html.
     */
    public function close(): void
    {
        $this->instance->registry->threads->disconnect($this->id);
        $this->transaction->disconnect();
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
        try {
            $statements = $this->split($sql);
        } catch (SqlError $error) {
            $this->diagnostics->clear();
            $error = (new CacheOptions())->reported($error, $sql, $this);
            if (!$error->recorded) {
                $this->diagnostics->error($error->getCode(), $error->getMessage());
            }
            $this->variables->rowCount = -1;

            return [$error];
        }
        $answers = [];
        foreach ($statements as $statement) {
            try {
                $reply = $this->execute($statement, $parameters, $prepared);
                array_push($answers, ...($reply instanceof \MySqlMemory\Result\Batch ? $reply->replies : [$reply]));
            } catch (SqlError $error) {
                $this->transaction->statements->abort();
                $this->variables->rowCount = -1;
                array_push($answers, ...$this->running->replies);
                $this->running->replies = [];
                if (!$error->recorded) {
                    $this->diagnostics->error($error->getCode(), $error->getMessage(), $error->signalled);
                }
                foreach ($error->following as [$code, $message]) {
                    $this->diagnostics->error($code, $message);
                }
                if ($this->transaction->unrestored) {
                    $this->transaction->unrestored = false;
                    $this->diagnostics->warning(\MySqlMemory\Error\Family\TransactionError::NotCompleteRollback, \MySqlMemory\Error\Family\TransactionError::NotCompleteRollback->message());
                }
                $answers[] = $error;
                break;
            }
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
            throw (new Syntax())->error($error, $sql);
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
        $this->text = $statement;
        $this->replayed = $prepared;
        try {
            $operation = $this->analyze($statement, $prepared, $parameters);
            $this->hinted = $prepared ? [] : $this->hinted;
            $command = (new Dispatcher())->command($operation->statement);
        } catch (SqlError $error) {
            $this->diagnostics->clear();
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
        try {
            $tree = $semantics->parser()->parse($statement);
        } catch (SourceException $error) {
            throw (new Syntax())->error($error, $statement);
        }
        if (!$prepared) {
            (new Syntax())->markers($tree, $statement);
        }
        (new Syntax())->temporals($tree, $this->modes());
        $this->dots = $this->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5744 ? (new Syntax())->dots($tree) : [];
        $this->hinted = (new \MySqlMemory\Hint\Hints())->syntax($tree, $statement, $this);
        $this->commented = str_contains($statement, '/*+');
        (new Syntax())->debugOnly($tree, $statement);
        $database = $this->variables->database;
        \MySqlMemory\Plan\Views::refreshAll($this->instance->dictionary, $this->settings());
        $typing = (new \MySqlMemory\Hint\Hints())->typing($tree, $statement, $this);
        try {
            $operation = $semantics->analyze($tree, $semantics->context($this->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $this->resolution($this->bound($tree, $parameters, $prepared))));
        } catch (ImplementationGap $gap) {
            throw new SqlError(StatementError::NotSupportedYet, StatementError::NotSupportedYet->message($gap->getMessage()), $gap);
        } catch (AnalysisException $error) {
            throw (new Syntax())->error($error, $statement);
        } finally {
            $typing->restore($this);
        }
        (new Syntax())->internal($operation->statement, $statement);

        return $operation;
    }

    /**
     * Answers the type of the value bound to each parameter marker of a statement, by the position of the marker.
     *
     * A statement prepared before any value is bound types each marker as the server types a lone
     * marker then: a VARCHAR of 16383 characters in the connection collation.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound in the order of the markers
     * @return array<int, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>
     */
    public function bound(\SqlParser\Parser\Node $tree, array $parameters, bool $prepared = false): array
    {
        $bound = [];
        $index = 0;
        $unbound = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(16383, $this->resolution()->connection, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
        foreach ($tree->tokens() as $token) {
            if ($token->name !== 'PARAM_MARKER') {
                continue;
            }
            if (isset($parameters[$index])) {
                $bound[$index] = $parameters[$index][1]->resolved();
            } elseif ($prepared) {
                $bound[$index] = $unbound;
            }
            $index++;
        }

        return $bound;
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

        return new Resolution($connection, (int) $this->variables->read('div_precision_increment'), $server, $schemas, (int) $this->variables->read('group_concat_max_len'), $users, $parameters, !$this->modes()->has('NO_UNSIGNED_SUBTRACTION'), is_string($client) ? Charset::named($client) : null, program: $this->program?->rows() ?? [], functions: $this->instance->dictionary->functions(), blockEncryptionMode: (string) $this->variables->read('block_encryption_mode'), timeNames: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Locale::named((string) $this->variables->read('lc_time_names')));
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
