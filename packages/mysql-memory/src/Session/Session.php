<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings as Resolution;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\Reply;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SyntaxException;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Contract\SearchPath;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Statement\Operation;

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
    public readonly Variables $variables;

    public readonly Diagnostics $diagnostics;

    public readonly Transaction $transaction;

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
        $this->variables = new Variables($instance->catalog, $instance->globals);
        $this->variables->connection = $id;
        $this->variables->account = $user . '@' . $host;
        $this->variables->definer = $user . '@%';
        $this->diagnostics = new Diagnostics();
        $this->transaction = new Transaction($instance->dictionary);
        if ($database !== null) {
            $this->use($database);
        }
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
            $this->diagnostics->error($error->getCode(), $error->getMessage());

            return [$error];
        }
        $answers = [];
        foreach ($statements as $statement) {
            try {
                $answers[] = $this->execute($statement, $parameters, $prepared);
            } catch (SqlError $error) {
                $this->transaction->abortStatement();
                $this->diagnostics->error($error->getCode(), $error->getMessage());
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
            throw ErrorCode::EmptyQuery->error();
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
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters
     *
     * @throws SqlError When the statement fails
     */
    public function execute(string $statement, array $parameters = [], bool $prepared = false): Reply
    {
        $operation = $this->analyze($statement, $prepared);
        $context = new Context($this->modes(), $this->diagnostics, $this->variables, microtime(true));
        $command = (new Dispatcher())->command($operation->statement);
        if ($command->clearsDiagnostics()) {
            $this->diagnostics->clear();
        }
        foreach ($operation->facts->warnings as $warning) {
            $this->diagnostics->warning($warning instanceof Deprecation ? $warning->code() : 1105, $warning->message());
        }
        (new Problems())->raise($operation, $this);
        $this->transaction->beginStatement();
        $reply = $command->execute($operation, $this, $context, new Connection($this->variables, $context, $this->user, $this->host, $this->id, $parameters));
        $this->transaction->endStatement();

        return $reply;
    }

    /**
     * Parses and resolves one statement against the tables of the server.
     *
     * @throws SqlError When the statement does not parse or does not resolve
     */
    public function analyze(string $statement, bool $prepared = false): Operation
    {
        $semantics = $this->semantics();
        try {
            $tree = $semantics->parser()->parse($statement);
        } catch (SourceException $error) {
            throw (new Syntax())->error($error, $statement);
        }
        if (!$prepared) {
            (new Syntax())->markers($tree, $statement);
        }
        $database = $this->variables->database;
        try {
            $operation = $semantics->analyze($tree, $semantics->context($this->instance->dictionary->declarations(), true, $database === '' ? null : new SearchPath($database), $this->resolution()));
        } catch (ImplementationGap $gap) {
            throw new SqlError(ErrorCode::NotSupportedYet, ErrorCode::NotSupportedYet->message($gap->getMessage()), $gap);
        } catch (AnalysisException $error) {
            throw (new Syntax())->error($error, $statement);
        }
        return $operation;
    }

    /**
     * Answers the session variables SQL Semantics resolves types with.
     */
    public function resolution(): Resolution
    {
        $schemas = [];
        foreach ($this->instance->dictionary->schemas as $schema) {
            $schemas[$schema->name] = Collation::named($schema->collation) ?? Collation::known('utf8mb4_0900_ai_ci');
        }
        $connection = Collation::named((string) $this->variables->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci');
        $server = Collation::named((string) $this->variables->read('collation_server'));

        $users = array_map(static fn (array $variable) => $variable[1]->resolved(), $this->variables->user);

        return new Resolution($connection, (int) $this->variables->read('div_precision_increment'), $server, $schemas, (int) $this->variables->read('group_concat_max_len'), $users);
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
        return SqlModes::parse((string) $this->variables->read('sql_mode')) ?? new SqlModes([]);
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
        if ($this->instance->dictionary->schema($database) === null) {
            throw ErrorCode::BadDatabase->error($database);
        }
        $this->variables->database = $database;
    }
}
