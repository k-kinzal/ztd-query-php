<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableAsSelectMutation;
use ZtdQuery\Shadow\ShadowStore;

#[UsesClass(ColumnDeclaration::class)]
#[UsesClass(ResultColumn::class)]
#[UsesClass(ResultSet::class)]
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
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
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
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
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
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
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
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
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
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
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
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']]);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertContains('id', $definition->columns);
        self::assertContains('name', $definition->columns);
        self::assertContains('email', $definition->columns);
    }

    public function testApplyResultSetCreatesEmptyTableFromMetadata(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            [],
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );

        $result = new ResultSet([], [
            new ResultColumn('id', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'int4')),
            new ResultColumn('name', new ColumnDeclaration(ColumnTypeFamily::TEXT, 'text')),
        ]);

        $mutation->applyResultSet($store, $result);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertSame(['id', 'name'], $definition->columns);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['id']->family);
        self::assertSame(ColumnTypeFamily::TEXT, $definition->typedColumns['name']->family);
        self::assertSame([], $store->get('users_copy'));
    }

    public function testApplyResultSetUsesMetadataTypesInsteadOfTextFallback(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();
        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['id'],
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );
        $result = new ResultSet(
            [['id' => 1]],
            [new ResultColumn('id', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'int4'))],
        );

        $mutation->applyResultSet($store, $result);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertSame('int4', $definition->columnTypes['id']);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['id']->family);
    }

    public function testApplyResultSetKeepsExplicitColumnNamesWithMetadataTypes(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();
        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['display_id'],
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );
        $result = new ResultSet(
            [['source_id' => 1]],
            [new ResultColumn('source_id', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'int4'))],
        );

        $mutation->applyResultSet($store, $result);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertSame(['display_id'], $definition->columns);
        self::assertSame(ColumnTypeFamily::INTEGER, $definition->typedColumns['display_id']->family);
    }

    public function testApplyKeepsParsedProjectionNamesWhenMetadataIsUnavailable(): void
    {
        $registry = new TableDefinitionRegistry();
        $store = new ShadowStore();
        $mutation = new CreateTableAsSelectMutation(
            'users_copy',
            ['display_name'],
            $registry,
            new ColumnDeclaration(ColumnTypeFamily::TEXT, 'fixture_text'),
        );

        $mutation->apply($store, [['source_name' => 'Alice']]);

        $definition = $registry->get('users_copy');
        self::assertNotNull($definition);
        self::assertSame(['display_name'], $definition->columns);
        self::assertSame('fixture_text', $definition->columnTypes['display_name']);
    }
}
