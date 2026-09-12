<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use mysqli;
use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use Override;
use ReturnTypeWillChange;
use SensitiveParameter;

/**
 * Test double for mysqli that allows configuring behavior without PHPUnit mocks.
 *
 * Since the custom PHPStan rule requires createMock() to target interfaces only,
 * this stub provides a concrete test double for mysqli delegation tests.
 */
class StubMysqli extends mysqli
{
    /**
     * Native calls and arguments observed by this test double.
     *
     * @var list<array{string, array<int, mixed>}>
     */
    public array $calls = [];
    /**
     * Configured prepare return for the native delegation test.
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
     * Configured real query return for the native delegation test.
     */
    public bool $realQueryReturn = true;

    /**
     * Configured multi query return for the native delegation test.
     */
    public bool $multiQueryReturn = true;

    /**
     * Configured begin transaction return for the native delegation test.
     */
    public bool $beginTransactionReturn = true;

    /**
     * Configured begin transaction called with flags for the native delegation test.
     */
    public ?int $beginTransactionCalledWithFlags = null;

    /**
     * Configured commit return for the native delegation test.
     */
    public bool $commitReturn = true;

    /**
     * Configured commit called with flags for the native delegation test.
     */
    public ?int $commitCalledWithFlags = null;

    /**
     * Configured rollback return for the native delegation test.
     */
    public bool $rollbackReturn = true;

    /**
     * Configured rollback called with flags for the native delegation test.
     */
    public ?int $rollbackCalledWithFlags = null;

    /**
     * Configured select db return for the native delegation test.
     */
    public bool $selectDbReturn = true;

    /**
     * Configured real escape string return for the native delegation test.
     */
    public string $realEscapeStringReturn = '';

    /**
     * @var mysqli_result|bool
     */
    public mysqli_result|bool $executeQueryReturn = true;

    /**
     * Configured close called for the native delegation test.
     */
    public bool $closeCalled = false;

    /**
     * @var int|string
     */
    public int|string $affectedRowsValue = 0;

    /**
     * Initialize an unconnected native handle with configurable responses.
     */
    public function __construct()
    {
        parent::__construct(...MySqlContainer::connectionParameters());
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function prepare(string $query): mysqli_stmt|false
    {
        $this->prepareCalledWith = $query;
        return $this->prepareReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): mysqli_result|bool
    {
        $this->queryCalledWith = $query;
        return $this->queryReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function real_query(string $query): bool
    {
        return $this->realQueryReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function multi_query(string $query): bool
    {
        return $this->multiQueryReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function begin_transaction(int $flags = 0, ?string $name = null): bool
    {
        $this->beginTransactionCalledWithFlags = $flags;
        return $this->beginTransactionReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $this->commitCalledWithFlags = $flags;
        return $this->commitReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function rollback(int $flags = 0, ?string $name = null): bool
    {
        $this->rollbackCalledWithFlags = $flags;
        return $this->rollbackReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->closeCalled = true;
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function select_db(string $database): bool
    {
        return $this->selectDbReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function real_escape_string(string $string): string
    {
        return $this->realEscapeStringReturn;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function escape_string(string $string): string
    {
        $this->calls[] = ['escape_string', func_get_args()];
        return $this->realEscapeStringReturn;
    }

    /**
     * @param array<mixed, mixed>|null $params
     */
    #[Override]
    public function execute_query(string $query, ?array $params = null): mysqli_result|bool
    {
        return $this->executeQueryReturn;
    }

    /**
     * Expose the configured native property value.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'affected_rows') {
            return $this->affectedRowsValue;
        }
        return null;
    }

    /**
     * Report whether a supported native property exists.
     */
    public function __isset(string $name): bool
    {
        return $name === 'affected_rows';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set_charset(string $charset): bool
    {
        $this->calls[] = ['set_charset', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function ping(): bool
    {
        $this->calls[] = ['ping', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function character_set_name(): string
    {
        $this->calls[] = ['character_set_name', func_get_args()];
        return 'utf8mb4';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function change_user(string $username, #[SensitiveParameter]
        string $password, ?string $database): bool
    {
        $this->calls[] = ['change_user', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function connect(?string $hostname = null, ?string $username = null, #[SensitiveParameter]
        ?string $password = null, ?string $database = null, ?int $port = null, ?string $socket = null): bool
    {
        $this->calls[] = ['connect', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function debug(string $options)
    {
        $this->calls[] = ['debug', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function dump_debug_info(): bool
    {
        $this->calls[] = ['dump_debug_info', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_charset(): ?object
    {
        $this->calls[] = ['get_charset', func_get_args()];
        return null;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_client_info(): string
    {
        $this->calls[] = ['get_client_info', func_get_args()];
        return 'test-client';
    }

    /**
     * {@inheritDoc}
     *
     * @return array<string, string>
     */
    #[Override]
    public function get_connection_stats(): array
    {
        $this->calls[] = ['get_connection_stats', func_get_args()];
        return ['bytes_sent' => '123'];
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_server_info(): string
    {
        $this->calls[] = ['get_server_info', func_get_args()];
        return '8.4.7';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_warnings(): mysqli_warning|false
    {
        $this->calls[] = ['get_warnings', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function init(): ?bool
    {
        $this->calls[] = ['init', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function kill(int $process_id): bool
    {
        $this->calls[] = ['kill', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function more_results(): bool
    {
        $this->calls[] = ['more_results', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function next_result(): bool
    {
        $this->calls[] = ['next_result', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function options(int $option, mixed $value): bool
    {
        $this->calls[] = ['options', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function real_connect(?string $hostname = null, ?string $username = null, #[SensitiveParameter]
        ?string $password = null, ?string $database = null, ?int $port = null, ?string $socket = null, int $flags = 0): bool
    {
        $this->calls[] = ['real_connect', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function reap_async_query(): mysqli_result|bool
    {
        $this->calls[] = ['reap_async_query', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function refresh(int $flags): bool
    {
        $this->calls[] = ['refresh', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function release_savepoint(string $name): bool
    {
        $this->calls[] = ['release_savepoint', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function savepoint(string $name): bool
    {
        $this->calls[] = ['savepoint', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function ssl_set(?string $key, ?string $certificate, ?string $ca_certificate, ?string $ca_path, ?string $cipher_algos)
    {
        $this->calls[] = ['ssl_set', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function stat(): string|false
    {
        $this->calls[] = ['stat', func_get_args()];
        return 'Threads: 1';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function stmt_init(): mysqli_stmt
    {
        $this->calls[] = ['stmt_init', func_get_args()];
        return StubMysqliStmt::create();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function store_result(int $mode = 0): mysqli_result|false
    {
        $this->calls[] = ['store_result', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function thread_safe(): bool
    {
        $this->calls[] = ['thread_safe', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function use_result(): mysqli_result|false
    {
        $this->calls[] = ['use_result', func_get_args()];
        return false;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function set_opt(int $option, mixed $value): bool
    {
        $this->calls[] = ['set_opt', func_get_args()];
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function autocommit(bool $enable): bool
    {
        $this->calls[] = ['autocommit', func_get_args()];
        return true;
    }
}
