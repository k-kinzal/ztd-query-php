<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\CreateTableMutation;
use ZtdQuery\Shadow\ShadowStore;

final class CreateTableMutationTest extends TestCase
{
    public function testApplyRegistersTableInSchema(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $createSql = 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))';
        $mutation = new CreateTableMutation('users', $createSql, $registry);
        $mutation->apply($store, []);

        $this->assertSame($createSql, $registry->get('users'));
    }

    public function testApplyEnsuresTableInShadowStore(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $mutation = new CreateTableMutation('users', 'CREATE TABLE users (id INT)', $registry);
        $mutation->apply($store, []);

        $this->assertSame([], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $registry = new SchemaRegistry();
        $mutation = new CreateTableMutation('users', 'CREATE TABLE users (id INT)', $registry);

        $this->assertSame('users', $mutation->tableName());
    }

    public function testApplyThrowsExceptionWhenTableExists(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT)');
        $store = new ShadowStore();

        $mutation = new CreateTableMutation('users', 'CREATE TABLE users (id INT, name VARCHAR(255))', $registry);

        $this->expectException(TableAlreadyExistsException::class);
        $this->expectExceptionMessage("Table 'users' already exists.");

        $mutation->apply($store, []);
    }

    public function testApplyWithIfNotExistsSkipsWhenTableExists(): void
    {
        $registry = new SchemaRegistry();
        $originalSql = 'CREATE TABLE users (id INT)';
        $registry->register('users', $originalSql);
        $store = new ShadowStore();

        $newSql = 'CREATE TABLE IF NOT EXISTS users (id INT, name VARCHAR(255))';
        $mutation = new CreateTableMutation('users', $newSql, $registry, true);
        $mutation->apply($store, []);

        // Original schema should be preserved
        $this->assertSame($originalSql, $registry->get('users'));
    }

    public function testApplyWithIfNotExistsCreatesWhenTableDoesNotExist(): void
    {
        $registry = new SchemaRegistry();
        $store = new ShadowStore();

        $createSql = 'CREATE TABLE IF NOT EXISTS users (id INT)';
        $mutation = new CreateTableMutation('users', $createSql, $registry, true);
        $mutation->apply($store, []);

        $this->assertSame($createSql, $registry->get('users'));
    }
}
