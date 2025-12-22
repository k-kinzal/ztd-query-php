<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ShadowStore::class)]
#[CoversClass(CreateTableLikeMutation::class)]
final class CreateTableLikeMutationTest extends TestCase
{
    public function testApplyCopiesSchemaFromSourceTable(): void
    {
        $registry = new TableDefinitionRegistry();
        $sourceDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $sourceDefinition);
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);
        $mutation->apply($store, []);

        $newDefinition = $registry->get('users_backup');
        self::assertNotNull($newDefinition);
        self::assertSame(['id', 'name'], $newDefinition->columns);
    }

    public function testApplyEnsuresNewTableInShadowStore(): void
    {
        $registry = new TableDefinitionRegistry();
        $sourceDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $sourceDefinition);
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);
        $mutation->apply($store, []);

        self::assertSame([], $store->get('users_backup'));
    }

    public function testTableNameReturnsNewTableName(): void
    {
        $registry = new TableDefinitionRegistry();
        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);

        self::assertSame('users_backup', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTargetTableExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $sourceDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $sourceDefinition);

        $backupDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users_backup', $backupDefinition);
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'users_backup' already exists.");

        $mutation->apply($store, []);
    }

    public function testApplyWithIfNotExistsSkipsWhenTableExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $sourceDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users', $sourceDefinition);

        $originalDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            ['id'],
            ['id'],
            [],
        );
        $registry->register('users_backup', $originalDefinition);
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry, true);
        $mutation->apply($store, []);

        $def = $registry->get('users_backup');
        self::assertNotNull($def);
        self::assertSame(['id'], $def->columns);
    }

    public function testApplyThrowsExceptionWhenSourceTableDoesNotExist(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Source table 'users' does not exist.");

        $mutation->apply($store, []);
    }
}
