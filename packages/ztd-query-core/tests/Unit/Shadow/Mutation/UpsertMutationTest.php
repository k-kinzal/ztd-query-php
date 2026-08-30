<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\Key\CandidateKeyConflict;
use ZtdQuery\Schema\Key\CandidateKeySet;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertOperator;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\Mutation\UpsertMutationRow;
use ZtdQuery\Shadow\Mutation\UpsertUpdate;
use ZtdQuery\Shadow\ShadowStore;

#[UsesClass(ShadowStore::class)]
#[UsesClass(UnsupportedSqlException::class)]
#[UsesClass(CandidateKeyConflict::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(UpsertExpression::class)]
#[UsesClass(UpsertMutationRow::class)]
#[CoversClass(UpsertMutation::class)]
#[UsesClass(\ZtdQuery\Schema\Key\CandidateKeyMatch::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\ConflictSearch::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertColumn::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertComparison::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\Upsert\UpsertTruth::class)]
#[UsesClass(UpsertUpdate::class)]
#[UsesClass(UpsertOperator::class)]
final class UpsertMutationTest extends TestCase
{
    public function testApplyInsertsNewRowWhenNoDuplicate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation('users', ['id']);
        $mutation->apply($store, [['id' => 2, 'name' => 'Bob', 'visits' => 1]]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
        self::assertSame('Bob', $rows[1]['name']);
    }

    public function testApplyUpdatesExistingRowOnDuplicate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation('users', ['id'], ['visits']);
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'visits' => 11]]);

        $rows = $store->get('users');
        self::assertCount(1, $rows);
        self::assertSame(11, $rows[0]['visits']);
    }

    public function testSkippedConditionalRowDoesNotStopFollowingRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'first', 'score' => 10],
        ]);
        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::column(UpsertColumnSource::Incoming, 'name')],
            updatePredicate: UpsertExpression::binary(
                UpsertExpressionKind::GreaterOrEqual,
                UpsertExpression::column(UpsertColumnSource::Existing, 'score'),
                UpsertExpression::literal(80),
            ),
        );

        $mutation->apply($store, [
            ['id' => 1, 'name' => 'skipped', 'score' => 20],
            ['id' => 2, 'name' => 'inserted', 'score' => 90],
        ]);

        self::assertSame([
            ['id' => 1, 'name' => 'first', 'score' => 10],
            ['id' => 2, 'name' => 'inserted', 'score' => 90],
        ], $store->get('users'));
        self::assertSame([
            ['id' => 2, 'name' => 'inserted', 'score' => 90],
        ], $mutation->resultRows());
    }

    public function testApplyUpdatesExistingRowOnUniqueKeyConflict(): void
    {
        $definition = new TableDefinition(
            ['id', 'email', 'name'],
            ['id' => 'INT', 'email' => 'TEXT', 'name' => 'TEXT'],
            ['id'],
            ['id'],
            ['users_email' => ['email']],
        );
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'email' => 'alice@example.com', 'name' => 'Alice']]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::column(UpsertColumnSource::Incoming, 'name')],
            $definition->candidateKeys(),
        );
        $mutation->apply($store, [['id' => 2, 'email' => 'alice@example.com', 'name' => 'Updated']]);

        self::assertSame([
            ['id' => 1, 'email' => 'alice@example.com', 'name' => 'Updated'],
        ], $store->get('users'));
    }

    public function testTableNameReturnsTableName(): void
    {
        $mutation = new UpsertMutation('users', ['id']);

        self::assertSame('users', $mutation->tableName());
    }

    public function testApplyWithUpdateValuesExpression(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['visits'],
            ['visits' => UpsertExpression::column(UpsertColumnSource::Incoming, 'visits')]
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'visits' => 15]]);

        $rows = $store->get('users');
        self::assertSame(15, $rows[0]['visits']);
    }

    public function testApplyWithLiteralUpdateValue(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'status' => 'pending'],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['status'],
            ['status' => UpsertExpression::literal('updated')]
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice', 'status' => 'ignored']]);

        $rows = $store->get('users');
        self::assertSame('updated', $rows[0]['status']);
    }

    public function testApplyUpdatesAllNonPrimaryColumnsWhenNoUpdateColumnsSpecified(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation('users', ['id']);
        $mutation->apply($store, [['id' => 1, 'name' => 'Alice Updated', 'visits' => 20]]);

        $rows = $store->get('users');
        self::assertSame('Alice Updated', $rows[0]['name']);
        self::assertSame(20, $rows[0]['visits']);
    }

    public function testApplyHandlesMixedInsertAndUpdate(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new UpsertMutation('users', ['id'], ['name']);
        $mutation->apply($store, [
            ['id' => 1, 'name' => 'Alice Updated'],
            ['id' => 2, 'name' => 'Bob'],
        ]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
        self::assertSame('Alice Updated', $rows[0]['name']);
        self::assertSame('Bob', $rows[1]['name']);
    }

    public function testApplyWithCompositePrimaryKey(): void
    {
        $store = new ShadowStore();
        $store->set('order_items', [
            ['order_id' => 1, 'product_id' => 100, 'quantity' => 1],
        ]);

        $mutation = new UpsertMutation('order_items', ['order_id', 'product_id'], ['quantity']);
        $mutation->apply($store, [['order_id' => 1, 'product_id' => 100, 'quantity' => 5]]);

        $rows = $store->get('order_items');
        self::assertCount(1, $rows);
        self::assertSame(5, $rows[0]['quantity']);
    }

    public function testApplyWithMissingPrimaryKeyInsertsNewRow(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new UpsertMutation('users', ['id']);
        $mutation->apply($store, [['name' => 'Bob']]);

        $rows = $store->get('users');
        self::assertCount(2, $rows);
    }

    public function testApplyWithExcludedColumnReference(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice', 'visits' => 10],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::column(UpsertColumnSource::Incoming, 'name')]
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Bob', 'visits' => 15]]);

        $rows = $store->get('users');
        self::assertSame('Bob', $rows[0]['name']);
    }

    public function testApplyWithExcludedQuotedColumnReference(): void
    {
        $store = new ShadowStore();
        $store->set('users', [
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::column(UpsertColumnSource::Incoming, 'name')]
        );
        $mutation->apply($store, [['id' => 1, 'name' => 'Charlie']]);

        $rows = $store->get('users');
        self::assertSame('Charlie', $rows[0]['name']);
    }

    public function testApplyAccumulatesExistingAndIncomingValues(): void
    {
        $store = new ShadowStore();
        $store->set('items', [
            ['id' => 1, 'quantity' => 100],
        ]);

        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['quantity'],
            ['quantity' => UpsertExpression::binary(
                UpsertExpressionKind::Add,
                UpsertExpression::column(UpsertColumnSource::Existing, 'quantity'),
                UpsertExpression::column(UpsertColumnSource::Incoming, 'quantity'),
            )],
        );
        $mutation->apply($store, [['id' => 1, 'quantity' => 5]]);

        self::assertSame([['id' => 1, 'quantity' => 105]], $store->get('items'));
    }

    public function testApplyAccumulatesSequentialConflictsAgainstLatestRow(): void
    {
        $store = new ShadowStore();
        $store->set('items', [
            ['id' => 1, 'quantity' => 100],
        ]);

        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['quantity'],
            ['quantity' => UpsertExpression::binary(
                UpsertExpressionKind::Add,
                UpsertExpression::column(UpsertColumnSource::Existing, 'quantity'),
                UpsertExpression::column(UpsertColumnSource::Incoming, 'quantity'),
            )],
        );
        $mutation->apply($store, [
            ['id' => 1, 'quantity' => 5],
            ['id' => 1, 'quantity' => 7],
        ]);

        self::assertSame([['id' => 1, 'quantity' => 112]], $store->get('items'));
    }

    public function testApplyUsesConflictPredicateAndReportsOnlyAppliedRows(): void
    {
        $store = new ShadowStore();
        $store->set('items', [
            ['id' => 1, 'name' => 'original', 'score' => 50],
        ]);
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::column(UpsertColumnSource::Incoming, 'name')],
            updatePredicate: UpsertExpression::binary(
                UpsertExpressionKind::GreaterOrEqual,
                UpsertExpression::column(UpsertColumnSource::Existing, 'score'),
                UpsertExpression::literal(80),
            ),
        );

        $mutation->apply($store, [['id' => 1, 'name' => 'skipped', 'score' => 95]]);
        self::assertSame([['id' => 1, 'name' => 'original', 'score' => 50]], $store->get('items'));
        self::assertSame([], $mutation->resultRows());

        $store->set('items', [['id' => 1, 'name' => 'original', 'score' => 85]]);
        $mutation->apply($store, [['id' => 1, 'name' => 'updated', 'score' => 95]]);
        self::assertSame([['id' => 1, 'name' => 'updated', 'score' => 85]], $store->get('items'));
        self::assertSame([['id' => 1, 'name' => 'updated', 'score' => 85]], $mutation->resultRows());
    }

    public function testApplyUsesDatabaseEvaluatedValuesAndStripsMetadata(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1, 'value' => 'original']]);
        $codec = new UpsertMutationRow();
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['value'],
            ['value' => null],
            databaseEvaluated: true,
            updateSqlValues: ['value' => 'unsupported(native_expression)'],
            updateSqlPredicate: 'unsupported(native_predicate)',
        );

        $mutation->apply($store, [[
            'id' => 1,
            'value' => 'incoming',
            $codec->valueColumn(0) => 'evaluated',
            $codec->predicateColumn() => 1,
        ]]);

        self::assertSame([['id' => 1, 'value' => 'evaluated']], $store->get('items'));
        self::assertSame([['id' => 1, 'value' => 'evaluated']], $mutation->resultRows());

        $mutation->apply($store, [[
            'id' => 2,
            'value' => 'inserted',
            $codec->valueColumn(0) => null,
            $codec->predicateColumn() => null,
        ]]);
        self::assertSame(['id' => 2, 'value' => 'inserted'], $store->get('items')[1]);
    }

    public function testDatabaseEvaluatedBatchReevaluatesAgainstLatestConflictRow(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1, 'quantity' => 100]]);
        $codec = new UpsertMutationRow();
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['quantity'],
            ['quantity' => UpsertExpression::binary(
                UpsertExpressionKind::Add,
                UpsertExpression::column(UpsertColumnSource::Existing, 'quantity'),
                UpsertExpression::column(UpsertColumnSource::Incoming, 'quantity'),
            )],
            databaseEvaluated: true,
            updateSqlValues: ['quantity' => 'quantity + EXCLUDED.quantity'],
        );

        $mutation->apply($store, [
            ['id' => 1, 'quantity' => 5, $codec->valueColumn(0) => 105],
            ['id' => 1, 'quantity' => 7, $codec->valueColumn(0) => 107],
        ]);

        self::assertSame([['id' => 1, 'quantity' => 112]], $store->get('items'));
    }

    public function testDatabaseEvaluatedPredicateFallsBackWhenMetadataIsMissing(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1, 'name' => 'original', 'score' => 50]]);
        $codec = new UpsertMutationRow();
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::column(UpsertColumnSource::Incoming, 'name')],
            updatePredicate: UpsertExpression::binary(
                UpsertExpressionKind::GreaterOrEqual,
                UpsertExpression::column(UpsertColumnSource::Existing, 'score'),
                UpsertExpression::literal(80),
            ),
            databaseEvaluated: true,
            updateSqlValues: ['name' => 'EXCLUDED.name'],
            updateSqlPredicate: 'score >= 80',
        );

        $mutation->apply($store, [
            [
                'id' => 1,
                'name' => 'skipped',
                'score' => 95,
                $codec->valueColumn(0) => 'skipped',
            ],
            [
                'id' => 2,
                'name' => 'inserted',
                'score' => 95,
                $codec->valueColumn(0) => null,
            ],
        ]);
        self::assertSame([
            ['id' => 1, 'name' => 'original', 'score' => 50],
            ['id' => 2, 'name' => 'inserted', 'score' => 95],
        ], $store->get('items'));

        $store->set('items', [['id' => 1, 'name' => 'original', 'score' => 85]]);
        $mutation->apply($store, [[
            'id' => 1,
            'name' => 'updated',
            'score' => 95,
            $codec->valueColumn(0) => 'updated',
        ]]);
        self::assertSame([['id' => 1, 'name' => 'updated', 'score' => 85]], $store->get('items'));
    }

    public function testDatabaseEvaluatedBatchDetectsConflictsWithRowsInsertedEarlierInStatement(): void
    {
        $store = new ShadowStore();
        $store->set('items', []);
        $codec = new UpsertMutationRow();
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['quantity'],
            ['quantity' => UpsertExpression::binary(
                UpsertExpressionKind::Add,
                UpsertExpression::column(UpsertColumnSource::Existing, 'quantity'),
                UpsertExpression::column(UpsertColumnSource::Incoming, 'quantity'),
            )],
            databaseEvaluated: true,
            updateSqlValues: ['quantity' => 'quantity + EXCLUDED.quantity'],
        );

        $mutation->apply($store, [
            ['id' => 1, 'quantity' => 5, $codec->valueColumn(0) => null],
            ['id' => 1, 'quantity' => 7, $codec->valueColumn(0) => null],
        ]);

        self::assertSame([['id' => 1, 'quantity' => 12]], $store->get('items'));
    }

    public function testDatabaseEvaluatedBatchRejectsStaleUnsupportedExpression(): void
    {
        $store = new ShadowStore();
        $store->set('items', [['id' => 1, 'quantity' => 100]]);
        $codec = new UpsertMutationRow();
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['quantity'],
            ['quantity' => null],
            databaseEvaluated: true,
            updateSqlValues: ['quantity' => 'COALESCE(quantity, 0) + EXCLUDED.quantity'],
        );

        $this->expectException(UnsupportedSqlException::class);
        $mutation->apply($store, [
            ['id' => 1, 'quantity' => 5, $codec->valueColumn(0) => 105],
            ['id' => 1, 'quantity' => 7, $codec->valueColumn(0) => 107],
        ]);
    }

    public function testConflictPredicateRestrictsIncomingAndExistingCandidateRows(): void
    {
        $store = new ShadowStore();
        $store->set('users', [[
            'email' => 'alice@example.com',
            'status' => 'inactive',
            'login_count' => 2,
        ]]);
        $mutation = new UpsertMutation(
            'users',
            [],
            ['login_count'],
            ['login_count' => UpsertExpression::binary(
                UpsertExpressionKind::Add,
                UpsertExpression::column(UpsertColumnSource::Existing, 'login_count'),
                UpsertExpression::literal(1),
            )],
            CandidateKeySet::fromSchema([], ['users_active_email' => ['email']]),
            conflictPredicate: UpsertExpression::binary(
                UpsertExpressionKind::Equal,
                UpsertExpression::column(UpsertColumnSource::Existing, 'status'),
                UpsertExpression::literal('active'),
            ),
        );

        $mutation->apply($store, [
            ['email' => 'alice@example.com', 'status' => 'active', 'login_count' => 5],
            ['email' => 'alice@example.com', 'status' => 'active', 'login_count' => 1],
            ['email' => 'alice@example.com', 'status' => 'inactive', 'login_count' => 9],
        ]);

        self::assertSame([
            ['email' => 'alice@example.com', 'status' => 'inactive', 'login_count' => 2],
            ['email' => 'alice@example.com', 'status' => 'active', 'login_count' => 6],
            ['email' => 'alice@example.com', 'status' => 'inactive', 'login_count' => 9],
        ], $store->get('users'));
    }
    public function testResultRowsAnswersNothingUntilTheStatementHasBeenApplied(): void
    {
        self::assertSame([], (new UpsertMutation('users', ['id']))->resultRows());
    }

    public function testResultRowsAnswersEveryRowTheStatementWroteOrLeftAsItWas(): void
    {
        $store = new ShadowStore();
        $store->set('users', [['id' => 1, 'name' => 'a']]);
        $mutation = new UpsertMutation(
            'users',
            ['id'],
            ['name'],
            ['name' => UpsertExpression::literal('b')],
            CandidateKeySet::fromSchema(['id']),
        );

        $mutation->apply($store, [['id' => 1, 'name' => 'incoming'], ['id' => 2, 'name' => 'c']]);

        self::assertSame(
            [['id' => 1, 'name' => 'b'], ['id' => 2, 'name' => 'c']],
            $mutation->resultRows(),
        );
    }

}
