<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\CandidateKeyConflict;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\SynchronizeMutation;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(SynchronizeMutation::class)]
#[UsesClass(CandidateKeyConflict::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(DuplicateKeyException::class)]
#[UsesClass(NotNullViolationException::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(TableDefinition::class)]
final class SynchronizeMutationTest extends TestCase
{
    public function testApplyReplacesTheCompleteTableState(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'old']]);
        $rows = [
            ['id' => 1, 'name' => 'updated'],
            ['id' => 2, 'name' => 'inserted'],
        ];

        $mutation = new SynchronizeMutation('users');
        $mutation->apply($store, $rows);

        self::assertSame($rows, $store->get('users'));
        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyRejectsDuplicateCandidateKeys(): void
    {
        $definition = new TableDefinition(
            ['id', 'email'],
            ['id' => 'INTEGER', 'email' => 'TEXT'],
            ['id'],
            ['id'],
            ['users_email_key' => ['email']],
        );
        $mutation = new SynchronizeMutation('users', $definition, 'MERGE INTO users');

        $this->expectException(DuplicateKeyException::class);

        $mutation->apply(new ShadowStore(), [
            ['id' => 1, 'email' => 'same@example.com'],
            ['id' => 2, 'email' => 'same@example.com'],
        ]);
    }

    public function testApplyRejectsNullInNotNullColumn(): void
    {
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new SynchronizeMutation('users', $definition, 'MERGE INTO users');

        $this->expectException(NotNullViolationException::class);

        $mutation->apply(new ShadowStore(), [['id' => null]]);
    }

    public function testAffectedRowsCountsMixedInsertUpdateAndDelete(): void
    {
        $definition = new TableDefinition(
            ['id', 'name'],
            ['id' => 'INTEGER', 'name' => 'TEXT'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new SynchronizeMutation('users', $definition);

        self::assertSame(3, $mutation->affectedRowCount(
            [
                ['id' => 3, 'name' => 'deleted'],
                ['id' => 1, 'name' => 'old'],
                ['id' => 4, 'name' => 'unchanged'],
            ],
            [
                ['id' => 1, 'name' => 'updated'],
                ['id' => 2, 'name' => 'inserted'],
                ['id' => 4, 'name' => 'unchanged'],
            ],
        ));
    }

    public function testAffectedRowsUsesMultisetDifferenceWithoutPrimaryKey(): void
    {
        $mutation = new SynchronizeMutation('logs');

        self::assertSame(2, $mutation->affectedRowCount(
            [['message' => 'same'], ['message' => 'old-one'], ['message' => 'old-two']],
            [['message' => 'same'], ['message' => 'new-one'], ['message' => 'new-two']],
        ));
    }

    public function testAffectedRowsWithoutPrimaryKeyIgnoresRowOrdering(): void
    {
        $mutation = new SynchronizeMutation('logs');

        self::assertSame(0, $mutation->affectedRowCount(
            [['message' => 'first'], ['message' => 'second']],
            [['message' => 'second'], ['message' => 'first']],
        ));
    }

    public function testAffectedRowsMatchesEveryPrimaryKeyAfterAnEarlierMatch(): void
    {
        $definition = new TableDefinition(
            ['id'],
            ['id' => 'INTEGER'],
            ['id'],
            ['id'],
            [],
        );
        $mutation = new SynchronizeMutation('users', $definition);
        $rows = [['id' => 1], ['id' => 2]];

        self::assertSame(0, $mutation->affectedRowCount($rows, $rows));
        self::assertSame(2, $mutation->affectedRowCount([['name' => 'missing']], [['id' => 1]]));
    }
}
