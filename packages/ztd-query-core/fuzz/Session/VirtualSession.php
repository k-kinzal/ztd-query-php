<?php

declare(strict_types=1);

namespace Fuzz\Session;

use LogicException;
use Tests\Fake\FakeConnection;
use Tests\Fake\FakeSqlRewriter;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\ResultSelectRunner;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Session;
use ZtdQuery\Shadow\Mutation\Row\DeleteMutation;
use ZtdQuery\Shadow\Mutation\Row\InsertMutation;
use ZtdQuery\Shadow\Mutation\Row\UpdateMutation;
use ZtdQuery\Shadow\Mutation\Table\CreateTableMutation;
use ZtdQuery\Shadow\Mutation\Table\DropTableMutation;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\ShadowTransactions;

/**
 * Exercises core Session state without SQL parsing or a production database adapter.
 */
final class VirtualSession
{
    /**
     * Actual virtual rows owned by this session.
     */
    public readonly ShadowStore $store;
    /**
     * Actual virtual schema owned by this session.
     */
    public readonly TableDefinitionRegistry $registry;
    /**
     * Core session under test.
     */
    public readonly Session $session;
    /**
     * Recording physical connection, which must receive no queries.
     */
    public readonly FakeConnection $connection;

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
        $this->session = new Session(new FakeSqlRewriter($this->store, $this->registry), $this->store, new ResultSelectRunner(), ZtdConfig::default(), $this->connection, new ShadowTransactions($this->store, $this->registry), $this->registry);
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
     * @throws \ZtdQuery\Connection\Exception\DatabaseException
     * @throws LogicException
     */
    public function apply(int $operation, string $table, int $id, mixed $value, string $name): void
    {
        if ($operation >= 3 && $operation <= 8) {
            match ($operation) {
                3 => $this->session->beginTransaction(),
                4 => $this->session->commitTransaction(),
                5 => $this->session->rollBackTransaction(),
                6 => $this->session->transactions()->savepoint($name),
                7 => $this->session->transactions()->rollBackTo($name),
                8 => $this->session->transactions()->release($name),
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
        $this->session->applyShadow($mutation, new ResultSet($rows, []), 'fuzz operation');
    }
}
