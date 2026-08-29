<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use Override;
use ReturnTypeWillChange;

/**
 * Test double for mysqli that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli delegation tests.
 */
class StubMysqli extends mysqli
{
    /**
     * @var mysqli_stmt|false The prepare return
     */
    public mysqli_stmt|false $prepareReturn = false;

    /**
     * @var string|null
     */
    public ?string $prepareCalledWith = null;

    /**
     * @var mysqli_result|bool
     */
    public mysqli_result|bool $queryReturn = true;

    /**
     * @var string|null
     */
    public ?string $queryCalledWith = null;

    /**
     * @var bool The real query return
     */
    public bool $realQueryReturn = true;

    /**
     * @var bool The multi query return
     */
    public bool $multiQueryReturn = true;

    /**
     * @var bool The begin transaction return
     */
    public bool $beginTransactionReturn = true;

    /**
     * @var ?int The begin transaction called with flags
     */
    public ?int $beginTransactionCalledWithFlags = null;

    /**
     * @var bool The commit return
     */
    public bool $commitReturn = true;

    /**
     * @var ?int The commit called with flags
     */
    public ?int $commitCalledWithFlags = null;

    /**
     * @var bool The rollback return
     */
    public bool $rollbackReturn = true;

    /**
     * @var ?int The rollback called with flags
     */
    public ?int $rollbackCalledWithFlags = null;

    /**
     * @var bool The select db return
     */
    public bool $selectDbReturn = true;

    /**
     * @var string The real escape string return
     */
    public string $realEscapeStringReturn = '';

    /**
     * @var mysqli_result|bool
     */
    public mysqli_result|bool $executeQueryReturn = true;

    /**
     * @var bool The close called
     */
    public bool $closeCalled = false;

    /**
     * @var int|string
     */
    public int|string $affectedRowsValue = 0;


    /**
     * @var bool The answer every plain call gives back
     */
    public bool $answersTrue = true;

    /**
     * @var string The answer every call that reads a name gives back
     */
    public string $name = 'utf8mb4';

    /**
     * @var array<string, mixed> The connection statistics
     */
    public array $connectionStats = ['bytes_sent' => 0];

    /**
     * @var mysqli_result|false The result a stored or used result gives back
     */
    public mysqli_result|false $storedResult = false;

    /**
     * @var string|false What the server says it is doing
     */
    public string|false $statusLine = 'Uptime: 1';

    /**
     * @var list<string> Names of the calls this was asked to make, in order
     */
    public array $calls = [];

    /**
     * Binds the instance to what it will work from.
     *
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Prepare.
     *
     * @param string $query
     * @return mysqli_stmt|false
     */
    #[Override]
    public function prepare(string $query): mysqli_stmt|false
    {
        $this->prepareCalledWith = $query;
        return $this->prepareReturn;
    }

    /**
     * Query.
     *
     * @param string $query
     * @param int $resultMode
     * @return mysqli_result|bool
     */
    #[Override]
    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $this->queryCalledWith = $query;
        return $this->queryReturn;
    }

    /**
     * Real_query.
     *
     * @param string $query
     * @return bool
     */
    #[Override]
    public function real_query(string $query): bool
    {
        return $this->realQueryReturn;
    }

    /**
     * Multi_query.
     *
     * @param string $query
     * @return bool
     */
    #[Override]
    public function multi_query(string $query): bool
    {
        return $this->multiQueryReturn;
    }

    /**
     * Begin_transaction.
     *
     * @param int $flags
     * @param ?string $name
     * @return bool
     */
    #[Override]
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $this->beginTransactionCalledWithFlags = $flags;
        return $this->beginTransactionReturn;
    }

    /**
     * Commit.
     *
     * @param int $flags
     * @param ?string $name
     * @return bool
     */
    #[Override]
    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $this->commitCalledWithFlags = $flags;
        return $this->commitReturn;
    }

    /**
     * Rollback.
     *
     * @param int $flags
     * @param ?string $name
     * @return bool
     */
    #[Override]
    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        $this->rollbackCalledWithFlags = $flags;
        return $this->rollbackReturn;
    }

    /**
     * Close.
     *
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->closeCalled = true;
        return true;
    }

    /**
     * Select_db.
     *
     * @param string $database
     * @return bool
     */
    #[Override]
    public function select_db(string $database): bool
    {
        return $this->selectDbReturn;
    }

    /**
     * Real_escape_string.
     *
     * @param string $string
     * @return string
     */
    #[Override]
    public function real_escape_string(string $string): string
    {
        return $this->realEscapeStringReturn;
    }

    /**
     * Escape_string.
     *
     * @param string $string
     * @return string
     */
    #[Override]
    public function escape_string(string $string): string
    {
        return $this->realEscapeStringReturn;
    }

    /**
     * @param array<mixed, mixed>|null $params
     */
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool
    {
        return $this->executeQueryReturn;
    }

    /**
     * __get.
     *
     * @param string $name
     */
    public function __get(string $name): mixed
    {
        if ($name === 'affected_rows') {
            return $this->affectedRowsValue;
        }
        return null;
    }

    /**
     * __isset.
     *
     * @param string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return $name === 'affected_rows';
    }

    /**
     * @param string $charset The charset
     * @return bool
     */
    #[Override]
    public function set_charset(string $charset): bool
    {
        $this->calls[] = 'set_charset:' . $charset;

        return $this->answersTrue;
    }

    /**
     * @return bool
     */
    #[Override]
    public function ping(): bool
    {
        $this->calls[] = 'ping';

        return $this->answersTrue;
    }

    /**
     * @return string
     */
    #[Override]
    public function character_set_name(): string
    {
        return $this->name;
    }

    /**
     * @param string $username The username
     * @param string $password The password
     * @param ?string $database The database
     * @return bool
     */
    #[Override]
    public function change_user(string $username, string $password, ?string $database): bool
    {
        $this->calls[] = 'change_user:' . $username;

        return $this->answersTrue;
    }

    /**
     * @param ?string $hostname The hostname
     * @param ?string $username The username
     * @param ?string $password The password
     * @param ?string $database The database
     * @param ?int $port The port
     * @param ?string $socket The socket
     * @return bool
     */
    #[Override]
    public function connect(?string $hostname = null, ?string $username = null, ?string $password = null, ?string $database = null, ?int $port = null, ?string $socket = null): bool
    {
        $this->calls[] = 'connect';

        return $this->answersTrue;
    }

    /**
     * @param string $options The options
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function debug(string $options)
    {
        $this->calls[] = 'debug:' . $options;

        return true;
    }

    /**
     * @return bool
     */
    #[Override]
    public function dump_debug_info(): bool
    {
        $this->calls[] = 'dump_debug_info';

        return $this->answersTrue;
    }

    /**
     * @return ?object
     */
    #[Override]
    public function get_charset(): ?object
    {
        return (object) ['charset' => $this->name];
    }

    /**
     * @return string
     */
    #[Override]
    public function get_client_info(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function get_connection_stats(): array
    {
        return $this->connectionStats;
    }

    /**
     * @return string
     */
    #[Override]
    public function get_server_info(): string
    {
        return $this->name;
    }

    /**
     * @return mysqli_warning|false
     */
    #[Override]
    public function get_warnings(): mysqli_warning|false
    {
        return false;
    }

    /**
     * @return ?bool
     */
    #[Override]
    public function init(): ?bool
    {
        $this->calls[] = 'init';

        return true;
    }

    /**
     * @param int $process_id The process id
     * @return bool
     */
    #[Override]
    public function kill(int $process_id): bool
    {
        $this->calls[] = 'kill:' . $process_id;

        return $this->answersTrue;
    }

    /**
     * @return bool
     */
    #[Override]
    public function more_results(): bool
    {
        return $this->answersTrue;
    }

    /**
     * @return bool
     */
    #[Override]
    public function next_result(): bool
    {
        $this->calls[] = 'next_result';

        return $this->answersTrue;
    }

    /**
     * @param int $option The option
     * @param mixed $value The value
     * @return bool
     */
    #[Override]
    public function options(int $option, mixed $value): bool
    {
        $this->calls[] = 'options:' . $option;

        return $this->answersTrue;
    }

    /**
     * @param ?string $hostname The hostname
     * @param ?string $username The username
     * @param ?string $password The password
     * @param ?string $database The database
     * @param ?int $port The port
     * @param ?string $socket The socket
     * @param int $flags The flags
     * @return bool
     */
    #[Override]
    public function real_connect(?string $hostname = null, ?string $username = null, ?string $password = null, ?string $database = null, ?int $port = null, ?string $socket = null, int $flags = 0): bool
    {
        $this->calls[] = 'real_connect';

        return $this->answersTrue;
    }

    /**
     * @return mysqli_result|bool
     */
    #[Override]
    public function reap_async_query(): mysqli_result|bool
    {
        return $this->storedResult;
    }

    /**
     * @param int $flags The flags
     * @return bool
     */
    #[Override]
    public function refresh(int $flags): bool
    {
        $this->calls[] = 'refresh:' . $flags;

        return $this->answersTrue;
    }

    /**
     * @param string $name The name
     * @return bool
     */
    #[Override]
    public function release_savepoint(string $name): bool
    {
        $this->calls[] = 'release_savepoint:' . $name;

        return $this->answersTrue;
    }

    /**
     * @param string $name The name
     * @return bool
     */
    #[Override]
    public function savepoint(string $name): bool
    {
        $this->calls[] = 'savepoint:' . $name;

        return $this->answersTrue;
    }

    /**
     * @param ?string $key The key
     * @param ?string $certificate The certificate
     * @param ?string $ca_certificate The ca certificate
     * @param ?string $ca_path The ca path
     * @param ?string $cipher_algos The cipher algos
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function ssl_set(?string $key, ?string $certificate, ?string $ca_certificate, ?string $ca_path, ?string $cipher_algos)
    {
        $this->calls[] = 'ssl_set';

        return true;
    }

    /**
     * @return string|false
     */
    #[Override]
    public function stat(): string|false
    {
        return $this->statusLine;
    }

    /**
     * @return bool
     */
    #[Override]
    public function thread_safe(): bool
    {
        return $this->answersTrue;
    }

    /**
     * @param int $mode The mode
     * @return mysqli_result|false
     */
    #[Override]
    public function store_result(int $mode = 0): mysqli_result|false
    {
        return $this->storedResult;
    }

    /**
     * @return mysqli_result|false
     */
    #[Override]
    public function use_result(): mysqli_result|false
    {
        return $this->storedResult;
    }

    /**
     * @param int $option The option
     * @param mixed $value The value
     * @return bool
     */
    #[Override]
    public function set_opt(int $option, mixed $value): bool
    {
        $this->calls[] = 'set_opt:' . $option;

        return $this->answersTrue;
    }

    /**
     * @return mysqli_stmt
     */
    #[Override]
    public function stmt_init(): mysqli_stmt
    {
        return StubMysqliStmt::create();
    }

    /**
     * @param bool $enable The enable
     * @return bool
     */
    #[Override]
    public function autocommit(bool $enable): bool
    {
        $this->calls[] = 'autocommit:' . ($enable ? '1' : '0');

        return $this->answersTrue;
    }
}
