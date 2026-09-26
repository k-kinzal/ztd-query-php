<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use LogicException;
use Tests\Fake\FakeConnection;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\QueryExecutor;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\Row\DeleteMutation;
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\Mutation\Row\UpdateMutation;
use ZtdQuery\Shadow\Mutation\Table\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\Table\DropTableMutation;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Compares two isolated core sessions with independent array models.
 * Four bytes select session/operation, table, identity, and opaque value/savepoint.
 */
final class SessionTarget
{
    /**
     * Actual virtual rows owned by this session.
     */
    private readonly ShadowStore $store;
    /**
     * Actual virtual schema owned by this session.
     */
    private readonly TableDefinitionRegistry $registry;
    /**
     * Core session under test.
     */
    private readonly QueryExecutor $executor;
    /**
     * Recording physical connection, which must receive no queries.
     */
    private readonly FakeConnection $connection;

    /**
     * Create one isolated core session with a recording connection.
     */
    public function __construct()
    {
        $this->store = new ShadowStore();
        $this->registry = new TableDefinitionRegistry();
        $this->connection = new FakeConnection();
        foreach (['items', 'other'] as $table) {
            $this->store->set($table, []);
            $this->registry->register($table, self::definition());
        }
        $this->executor = new QueryExecutor($this->connection, new \Tests\Fake\FakePlatform(), session: new \ZtdQuery\Session($this->store, $this->registry));
    }

    /**
     * Return the row contract used by core mutation operations.
     */
    public static function definition(): TableDefinition
    {
        return new TableDefinition(['id', 'value'], ['id' => 'INTEGER', 'value' => 'TEXT'], ['id'], ['id'], []);
    }

    /**
     * Apply one mutation or transaction through the core session.
     * @param int|string|null|array{opaque: int} $value
     * @throws DatabaseException
     * @throws LogicException
     */
    public function apply(int $operation, string $table, int $id, int|string|array|null $value, string $name): void
    {
        if ($operation >= 3 && $operation <= 8) {
            match ($operation) {
                3 => $this->executor->session()->beginTransaction(),
                4 => $this->executor->session()->commitTransaction(),
                5 => $this->executor->session()->rollBackTransaction(),
                6 => $this->executor->session()->transactions()->savepoint($name),
                7 => $this->executor->session()->transactions()->rollBackTo($name),
                8 => $this->executor->session()->transactions()->release($name),
            };
            return;
        }
        $rows = [['id' => $id, 'value' => $value]];
        if ($operation === 11 || $operation === 12) {
            $rows = [['id' => 1000 + $id, 'value' => $value], ['id' => $operation === 11 ? 1000 + $id : null, 'value' => $value]];
        }
        $mutation = match ($operation) {
            0, 11, 12 => new InsertMutation($table, ['id'], tableDefinition: self::definition(), validateConstraints: true),
            1 => new UpdateMutation($table, ['id'], self::definition(), validateConstraints: true),
            2 => new DeleteMutation($table, ['id']),
            9 => new CreateTableMutation('temporary', self::definition(), $this->registry, 'CREATE', true),
            10 => new DropTableMutation('temporary', $this->registry, 'DROP', true),
            default => throw new LogicException('Unrecognized core operation.'),
        };
        $this->executor->applyShadow($mutation, new ResultSet($rows, []), 'fuzz operation');
    }

    /**
     * @var array<string, array<int, array{id: int, value: int|string|null|array{opaque: int}}>>
     */
    private array $tables = ['items' => [], 'other' => []];
    /**
     * @var list<array{name: string|null, tables: array<string, array<int, array{id: int, value: int|string|null|array{opaque: int}}>>}>
     */
    private array $checkpoints = [];

    /**
     * Advance the plain-array model or return the required rejection category.
     * @param int|string|null|array{opaque: int} $value
     */
    public function modelApply(int $operation, string $table, int $id, int|string|array|null $value, string $name): ?string
    {
        if ($operation === 0) {
            if (isset($this->tables[$table][$id])) {
                return 'unique';
            }
            $this->tables[$table][$id] = ['id' => $id, 'value' => $value];
        } elseif ($operation === 1 && isset($this->tables[$table][$id])) {
            $this->tables[$table][$id]['value'] = $value;
        } elseif ($operation === 2) {
            unset($this->tables[$table][$id]);
        } elseif ($operation === 9) {
            $this->tables['temporary'] ??= [];
        } elseif ($operation === 10) {
            unset($this->tables['temporary']);
        } elseif ($operation === 11 || $operation === 12) {
            return $operation === 11 ? 'unique' : 'not-null';
        } elseif ($operation >= 3 && $operation <= 8) {
            $this->modelTransaction($operation, $name);
        }
        return null;
    }

    /**
     * Apply transaction and savepoint semantics to model checkpoints.
     */
    public function modelTransaction(int $operation, string $name): void
    {
        if ($operation === 3 && $this->checkpoints === []) {
            $this->checkpoints[] = ['name' => null, 'tables' => $this->tables];
        } elseif ($operation === 4) {
            $this->checkpoints = [];
        } elseif ($operation === 5 && $this->checkpoints !== []) {
            $this->tables = $this->checkpoints[0]['tables'];
            $this->checkpoints = [];
        } elseif ($operation >= 6) {
            $position = array_search($name, array_column($this->checkpoints, 'name'), true);
            if ($position !== false) {
                if ($operation === 7) {
                    $this->tables = $this->checkpoints[$position]['tables'];
                }
                $this->checkpoints = array_slice($this->checkpoints, 0, $position + ($operation === 7 ? 1 : 0));
            }
            if ($operation === 6) {
                $this->checkpoints[] = ['name' => $name, 'tables' => $this->tables];
            }
        }
    }
    /**
     * Decode actions for two sessions and verify both after every operation.
     * @throws Error
     */
    public function __invoke(string $input): void
    {
        $executors = [new self(), new self()];
        foreach (str_split(($input === '' ? "\0" : substr($input, 0, 512)), 4) as $step => $chunk) {
            $chunk = str_pad($chunk, 4, "\0");
            $choice = ord($chunk[0]);
            $executor = $choice % 2;
            $operation = intdiv($choice, 2) % 13;
            $table = (ord($chunk[1]) % 2) === 0 ? 'items' : 'other';
            $id = (ord($chunk[2]) % 8) + 1;
            $valueChoice = ord($chunk[3]);
            $value = [$valueChoice, (string) $valueChoice, null, '', "O'Brien", ['opaque' => $valueChoice]][$valueChoice % 6];
            $name = 'point_' . ($valueChoice % 3);
            $expected = $executors[$executor]->modelApply($operation, $table, $id, $value, $name);
            $failure = null;
            try {
                $executors[$executor]->apply($operation, $table, $id, $value, $name);
            } catch (DatabaseException $error) {
                $failure = $error;
            }
            if ($expected !== null) {
                $cause = $failure?->getPrevious();
                if (!(($expected === 'unique' && $cause instanceof DuplicateKeyException)
                    || ($expected === 'not-null' && $cause instanceof NotNullViolationException))) {
                    throw new Error('Wrong rejection for ' . $expected, 0, $failure);
                }
            } elseif ($failure !== null) {
                throw new Error('Valid core operation was rejected.', 0, $failure);
            }
            foreach ($executors as $actual) {
                $actual->verify();
            }
        }
    }

    /**
     * Compare both row and catalog state, including after rejected batches.
     * @throws Error When the core differs from the independent model
     */
    public function verify(): void
    {
        $expected = array_map(array_values(...), $this->tables);
        if ($expected !== $this->store->getAll()
            || array_keys($expected) !== array_keys($this->registry->getAll())) {
            throw new Error('Core rows or catalog differ from the independent model.');
        }
        foreach (array_keys($expected) as $table) {
            if ($this->executor->session()->tableDefinition($table)?->columns !== ['id', 'value']) {
                throw new Error('Rollback lost the column definitions.');
            }
        }
        if ($this->connection->queries !== []) {
            throw new Error('A core state operation touched the physical connection.');
        }
    }
}
