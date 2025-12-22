<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;

final class UpdateMutationTest extends TestCase
{
    public function testApplyReplacesRowsByPrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $mutation = new UpdateMutation('users', ['id']);
        $mutation->apply($store, [
            ['id' => 2, 'name' => 'Bobby'],
        ]);

        $this->assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bobby'],
        ], $store->get('users'));
        $this->assertSame('users', $mutation->tableName());
    }

    public function testValidateNotNullThrowsException(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new UpdateMutation(
            'users',
            ['id'],
            $registry,
            'UPDATE users SET name = NULL WHERE id = 1',
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
        $store->set('users', [
            ['id' => 1, 'email' => 'alice@example.com'],
            ['id' => 2, 'email' => 'bob@example.com'],
        ]);

        $mutation = new UpdateMutation(
            'users',
            ['id'],
            $registry,
            'UPDATE users SET email = "alice@example.com" WHERE id = 2',
            true
        );

        $this->expectException(DuplicateKeyException::class);
        $this->expectExceptionMessageMatches("/Duplicate entry.*alice@example.com.*for key 'email_UNIQUE'/");

        // Trying to update id=2 with an email that already exists for id=1
        $mutation->apply($store, [['id' => 2, 'email' => 'alice@example.com']]);
    }

    public function testValidationDisabledByDefaultAllowsNullInNotNullColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL)');

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        // validateConstraints defaults to false
        $mutation = new UpdateMutation('users', ['id'], $registry);
        $mutation->apply($store, [['id' => 1, 'name' => null]]);

        // Update should succeed (no validation)
        $this->assertNull($store->get('users')[0]['name']);
    }

    public function testUpdateSameRowUniqueDoesNotThrow(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255) UNIQUE)');

        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'email' => 'alice@example.com'],
        ]);

        $mutation = new UpdateMutation(
            'users',
            ['id'],
            $registry,
            'UPDATE users SET email = "alice@example.com" WHERE id = 1',
            true
        );

        // Updating same row with same email value should not throw
        $mutation->apply($store, [['id' => 1, 'email' => 'alice@example.com']]);

        $this->assertSame('alice@example.com', $store->get('users')[0]['email']);
    }
}
