<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;

final class InsertMutationTest extends TestCase
{
    public function testApplyAppendsRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new InsertMutation('users');
        $mutation->apply($store, [['id' => 2]]);

        $this->assertSame([['id' => 1], ['id' => 2]], $store->get('users'));
        $this->assertSame('users', $mutation->tableName());
    }

    public function testInsertIgnoreSkipsDuplicates(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new InsertMutation('users', ['id'], true);
        $mutation->apply($store, [['id' => 1, 'name' => 'Bob'], ['id' => 2, 'name' => 'Carol']]);

        // Only non-duplicate row should be inserted
        $this->assertCount(2, $store->get('users'));
        $this->assertSame('Alice', $store->get('users')[0]['name']);
        $this->assertSame('Carol', $store->get('users')[1]['name']);
    }

    public function testValidatePrimaryKeyDuplicateThrowsException(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $registry,
            'INSERT INTO users (id, name) VALUES (1, "Bob")',
            true
        );

        $this->expectException(DuplicateKeyException::class);
        $this->expectExceptionMessage("Duplicate entry '1' for key 'PRIMARY'");

        $mutation->apply($store, [['id' => 1, 'name' => 'Bob']]);
    }

    public function testValidateNotNullThrowsException(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        $store = new ShadowStore();

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $registry,
            'INSERT INTO users (id, name) VALUES (1, NULL)',
            true
        );

        $this->expectException(NotNullViolationException::class);
        $this->expectExceptionMessage("Column 'name' in table 'users' cannot be NULL");

        $mutation->apply($store, [['id' => 1, 'name' => null]]);
    }

    public function testValidateUniqueThrowsException(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255) UNIQUE)');

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'email' => 'alice@example.com']]);

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $registry,
            'INSERT INTO users (id, email) VALUES (2, "alice@example.com")',
            true
        );

        $this->expectException(DuplicateKeyException::class);
        $this->expectExceptionMessageMatches("/Duplicate entry.*alice@example.com.*for key 'email_UNIQUE'/");

        $mutation->apply($store, [['id' => 2, 'email' => 'alice@example.com']]);
    }

    public function testValidationDisabledByDefaultAllowsDuplicates(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        // validateConstraints defaults to false
        $mutation = new InsertMutation('users', ['id'], false, $registry);
        $mutation->apply($store, [['id' => 1, 'name' => null]]);

        // Both rows should be inserted (no validation)
        $this->assertCount(2, $store->get('users'));
    }

    public function testUniqueConstraintAllowsNull(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255) UNIQUE)');

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'email' => null]]);

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $registry,
            'INSERT INTO users (id, email) VALUES (2, NULL)',
            true
        );

        // Multiple NULL values are allowed in UNIQUE columns
        $mutation->apply($store, [['id' => 2, 'email' => null]]);

        $this->assertCount(2, $store->get('users'));
    }

    public function testApplyInsertsMultipleRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);

        $mutation = new InsertMutation('users');
        $mutation->apply($store, [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Carol'],
        ]);

        $this->assertCount(3, $store->get('users'));
    }

    public function testApplyWithCompositePrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('order_items', [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 1],
        ]);

        $mutation = new InsertMutation('order_items', ['order_id', 'product_id'], true);
        $mutation->apply($store, [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 5], // Duplicate - should be skipped
            ['order_id' => 1, 'product_id' => 200, 'quantity' => 2], // New - should be inserted
        ]);

        $this->assertCount(2, $store->get('order_items'));
        $this->assertSame(1, $store->get('order_items')[0]['quantity']); // Original unchanged
    }

    public function testApplyInsertsToEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');

        $mutation = new InsertMutation('users');
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        $this->assertCount(1, $store->get('users'));
        $this->assertSame('Alice', $store->get('users')[0]['name']);
    }
}
