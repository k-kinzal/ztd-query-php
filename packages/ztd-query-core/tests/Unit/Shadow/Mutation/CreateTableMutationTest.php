<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(TableAlreadyExistsException::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ShadowStore::class)]
#[CoversClass(CreateTableMutation::class)]
final class CreateTableMutationTest extends TestCase
{
    public function testApplyRegistersTableInSchema(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $definition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new CreateTableMutation('users', $definition, $registry, 'fixture statement');
        $mutation->apply($store, []);

        self::assertNotNull($registry->get('users'));
        self::assertSame(['id', 'name'], $registry->get('users')->columns);
    }

    public function testApplyEnsuresTableInShadowStore(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new CreateTableMutation('users', $definition, $registry, 'fixture statement');
        $mutation->apply($store, []);

        self::assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new CreateTableMutation('users', $definition, $registry, 'fixture statement');

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $existingDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $existingDefinition);
        $store = new ShadowStore();

        $newDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new CreateTableMutation('users', $newDefinition, $registry, 'fixture statement');

        try {
            $mutation->apply($store, []);
            self::fail('Expected duplicate virtual table creation to fail.');
        } catch (TableAlreadyExistsException $exception) {
            self::assertSame("Table 'users' already exists.", $exception->getMessage());
            self::assertSame('fixture statement', $exception->getSql());
        }
    }

    public function testApplyWithIfNotExistsSkipsWhenTableExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $originalDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $originalDefinition);
        $store = new ShadowStore();

        $newDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new CreateTableMutation('users', $newDefinition, $registry, 'fixture statement', true);
        $mutation->apply($store, []);

        $def = $registry->get('users');
        self::assertNotNull($def);
        self::assertSame(['id'], $def->columns);
    }

    public function testApplyWithIfNotExistsCreatesWhenTableDoesNotExist(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new CreateTableMutation('users', $definition, $registry, 'fixture statement', true);
        $mutation->apply($store, []);

        self::assertNotNull($registry->get('users'));
        self::assertSame(['id'], $registry->get('users')->columns);
    }
}
