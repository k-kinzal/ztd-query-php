<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ZtdQuery\Schema\ColumnType;

#[UsesClass(ColumnType::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ShadowStore::class)]
#[CoversClass(CreateTableAsSelectMutation::class)]
final class CreateTableAsSelectMutationTest extends TestCase
{
    public function testApplyRegistersTableWithColumnsFromSelect(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['id', 'name'],
            $registry
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
    }

    public function testApplyStoresRowsFromSelectResult(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['id', 'name'],
            $registry
        );
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $mutation->apply($store, $rows);

        self::assertSame($rows, $store->get('users_copy'));
    }

    public function testTableNameReturnsNewTableName(): void
    {
        $registry = new TableDefinitionRegistry();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['id'],
            $registry
        );

        self::assertSame('users_copy', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $existingDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            [],
            [],
            [],
        );
        $registry->register('users_copy', $existingDefinition);
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['id'],
            $registry
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'users_copy' already exists.");

        $mutation->apply($store, [['id' => 1]]);
    }

    public function testApplyWithIfNotExistsSkipsWhenTableExists(): void
    {
        $registry = new TableDefinitionRegistry();
        $originalDefinition = new TableDefinition(
            ['id'],
            ['id' => 'INT'],
            [],
            [],
            [],
        );
        $registry->register('users_copy', $originalDefinition);
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['id', 'name'],
            $registry,
            true
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        $def = $registry->get('users_copy');
        self::assertNotNull($def);
        self::assertSame(['id'], $def->columns);
    }

    public function testApplyInfersColumnsFromResultRowsForSelectStar(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            [],
            $registry
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
        self::assertContains('email', $definition->columns);
    }

    public function testApplyThrowsExceptionWhenNoColumnsCanBeDetermined(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            [],
            $registry
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine columns for CREATE TABLE AS SELECT.');

        $mutation->apply($store, []);
    }
}
