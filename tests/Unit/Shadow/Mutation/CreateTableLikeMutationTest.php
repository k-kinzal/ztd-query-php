<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableLikeMutation;
use ZtdQuery\Shadow\ShadowStore;

final class CreateTableLikeMutationTest extends TestCase
{
    public function testApplyCopiesSchemaFromSourceTable(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);
        $mutation->apply($store, []);

        $newSchema = $registry->get('users_backup');
        $this->assertNotNull($newSchema);
        $this->assertStringContainsString('users_backup', $newSchema);
    }

    public function testApplyEnsuresNewTableInShadowStore(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT)');
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);
        $mutation->apply($store, []);

        $this->assertSame([], $store->get('users_backup'));
    }

    public function testTableNameReturnsNewTableName(): void
    {
        $registry = new SchemaRegistry();
        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);

        $this->assertSame('users_backup', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTargetTableExists(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT)');
        $registry->register('users_backup', 'CREATE TABLE users_backup (id INT)');
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Table 'users_backup' already exists.");

        $mutation->apply($store, []);
    }

    public function testApplyWithIfNotExistsSkipsWhenTableExists(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT, name VARCHAR(255))');
        $originalSql = 'CREATE TABLE users_backup (id INT)';
        $registry->register('users_backup', $originalSql);
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry, true);
        $mutation->apply($store, []);

        // Original schema should be preserved
        $this->assertSame($originalSql, $registry->get('users_backup'));
    }

    public function testApplyThrowsExceptionWhenSourceTableDoesNotExist(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableLikeMutation('users_backup', 'users', $registry);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Source table 'users' does not exist.");

        $mutation->apply($store, []);
    }
}
