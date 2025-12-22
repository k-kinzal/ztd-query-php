<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(DuplicateKeyException::class)]
#[UsesClass(NotNullViolationException::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(ShadowStore::class)]
#[CoversClass(UpdateMutation::class)]
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

        self::assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bobby'],
        ], $store->get('users'));
        self::assertSame('users', $mutation->tableName());
    }

    public function testValidateNotNullThrowsException(): void
    {
        $tableDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id', 'name'],
            [],
        );

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new UpdateMutation(
            'users',
            ['id'],
            $tableDefinition,
            'UPDATE users SET name = NULL WHERE id = 1',
            true
        );

        $this->expectException(NotNullViolationException::class);
        $this->expectExceptionMessage("Column 'name' in table 'users' cannot be NULL");

        $mutation->apply($store, [['id' => 1, 'name' => null]]);
    }

    public function testValidateUniqueThrowsException(): void
    {
        $tableDefinition = new TableDefinition(
            ['id', 'email'],
            ['id' => 'INT', 'email' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            ['email_UNIQUE' => ['email']],
        );

        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'email' => 'alice@example.com'],
            ['id' => 2, 'email' => 'bob@example.com'],
        ]);

        $mutation = new UpdateMutation(
            'users',
            ['id'],
            $tableDefinition,
            'UPDATE users SET email = "alice@example.com" WHERE id = 2',
            true
        );

        $this->expectException(DuplicateKeyException::class);
        $this->expectExceptionMessageMatches("/Duplicate entry.*alice@example.com.*for key 'email_UNIQUE'/");

        $mutation->apply($store, [['id' => 2, 'email' => 'alice@example.com']]);
    }

    public function testValidationDisabledByDefaultAllowsNullInNotNullColumn(): void
    {
        $tableDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id', 'name'],
            [],
        );

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new UpdateMutation('users', ['id'], $tableDefinition);
        $mutation->apply($store, [['id' => 1, 'name' => null]]);

        self::assertNull($store->get('users')[0]['name']);
    }

    public function testUpdateSameRowUniqueDoesNotThrow(): void
    {
        $tableDefinition = new TableDefinition(
            ['id', 'email'],
            ['id' => 'INT', 'email' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            ['email_UNIQUE' => ['email']],
        );

        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'email' => 'alice@example.com'],
        ]);

        $mutation = new UpdateMutation(
            'users',
            ['id'],
            $tableDefinition,
            'UPDATE users SET email = "alice@example.com" WHERE id = 1',
            true
        );

        $mutation->apply($store, [['id' => 1, 'email' => 'alice@example.com']]);

        self::assertSame('alice@example.com', $store->get('users')[0]['email']);
    }
}
