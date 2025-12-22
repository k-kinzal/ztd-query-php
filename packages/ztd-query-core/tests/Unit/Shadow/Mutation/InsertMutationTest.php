<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\ShadowStore;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(DuplicateKeyException::class)]
#[UsesClass(NotNullViolationException::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(ShadowStore::class)]
#[CoversClass(InsertMutation::class)]
final class InsertMutationTest extends TestCase
{
    public function testApplyAppendsRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1]]);

        $mutation = new InsertMutation('users');
        $mutation->apply($store, [['id' => 2]]);

        self::assertSame([['id' => 1], ['id' => 2]], $store->get('users'));
        self::assertSame('users', $mutation->tableName());
    }

    public function testInsertIgnoreSkipsDuplicates(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new InsertMutation('users', ['id'], true);
        $mutation->apply($store, [['id' => 1, 'name' => 'Bob'], ['id' => 2, 'name' => 'Carol']]);

        self::assertCount(2, $store->get('users'));
        self::assertSame('Alice', $store->get('users')[0]['name']);
        self::assertSame('Carol', $store->get('users')[1]['name']);
    }

    public function testValidatePrimaryKeyDuplicateThrowsException(): void
    {
        $tableDefinition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INT', 'name' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            [],
        );

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'Alice']]);

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $tableDefinition,
            'INSERT INTO users (id, name) VALUES (1, "Bob")',
            true
        );

        $this->expectException(DuplicateKeyException::class);
        $this->expectExceptionMessage("Duplicate entry '1' for key 'PRIMARY'");

        $mutation->apply($store, [['id' => 1, 'name' => 'Bob']]);
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

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $tableDefinition,
            'INSERT INTO users (id, name) VALUES (1, NULL)',
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
        $store->set('users', [['id' => 1, 'email' => 'alice@example.com']]);

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $tableDefinition,
            'INSERT INTO users (id, email) VALUES (2, "alice@example.com")',
            true
        );

        $this->expectException(DuplicateKeyException::class);
        $this->expectExceptionMessageMatches("/Duplicate entry.*alice@example.com.*for key 'email_UNIQUE'/");

        $mutation->apply($store, [['id' => 2, 'email' => 'alice@example.com']]);
    }

    public function testValidationDisabledByDefaultAllowsDuplicates(): void
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

        $mutation = new InsertMutation('users', ['id'], false, $tableDefinition);
        $mutation->apply($store, [['id' => 1, 'name' => null]]);

        self::assertCount(2, $store->get('users'));
    }

    public function testUniqueConstraintAllowsNull(): void
    {
        $tableDefinition = new TableDefinition(
            ['id', 'email'],
            ['id' => 'INT', 'email' => 'VARCHAR(255)'],
            ['id'],
            ['id'],
            ['email_UNIQUE' => ['email']],
        );

        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'email' => null]]);

        $mutation = new InsertMutation(
            'users',
            ['id'],
            false,
            $tableDefinition,
            'INSERT INTO users (id, email) VALUES (2, NULL)',
            true
        );

        $mutation->apply($store, [['id' => 2, 'email' => null]]);

        self::assertCount(2, $store->get('users'));
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

        self::assertCount(3, $store->get('users'));
    }

    public function testApplyWithCompositePrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('order_items', [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 1],
        ]);

        $mutation = new InsertMutation('order_items', ['order_id', 'product_id'], true);
        $mutation->apply($store, [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 5],
            ['order_id' => 1, 'product_id' => 200, 'quantity' => 2],
        ]);

        self::assertCount(2, $store->get('order_items'));
        self::assertSame(1, $store->get('order_items')[0]['quantity']);
    }

    public function testApplyInsertsToEmptyTable(): void
    {
        $store = new ShadowStore();
        $store->ensure('users');

        $mutation = new InsertMutation('users');
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice']]);

        self::assertCount(1, $store->get('users'));
        self::assertSame('Alice', $store->get('users')[0]['name']);
    }
}
