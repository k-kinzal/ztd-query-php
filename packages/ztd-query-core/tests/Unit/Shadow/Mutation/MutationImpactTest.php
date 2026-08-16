<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\AffectedRowsMode;
use ZtdQuery\Schema\CandidateKeySet;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MutationImpact;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\ReplaceMutation;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertMutation;
use ZtdQuery\Shadow\Mutation\UpdateMutation;

#[CoversClass(MutationImpact::class)]
#[UsesClass(CandidateKeySet::class)]
#[UsesClass(DeleteMutation::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(MutationRowIdentity::class)]
#[UsesClass(ReplaceMutation::class)]
#[UsesClass(UpsertExpression::class)]
#[UsesClass(UpsertMutation::class)]
#[UsesClass(UpdateMutation::class)]
final class MutationImpactTest extends TestCase
{
    public function testInsertImpactContainsOnlyRowsActuallyAdded(): void
    {
        $before = [['id' => 1]];
        $after = [['id' => 1], ['id' => 2]];
        $impact = new MutationImpact(new InsertMutation('items'), $before, [['id' => 2]], $after);

        self::assertSame(1, $impact->affectedRowCount(AffectedRowsMode::Changed));
        self::assertSame([['id' => 2]], $impact->returningRows());
        self::assertTrue($impact->isInsertLike());
    }

    public function testNoOpUpdateUsesPlatformRowCountMode(): void
    {
        $row = ['id' => 1, 'name' => 'same'];
        $reordered = ['name' => 'same', 'id' => 1];
        $impact = new MutationImpact(new UpdateMutation('items', ['id']), [$row], [$reordered], [$reordered]);

        self::assertSame(0, $impact->affectedRowCount(AffectedRowsMode::Changed));
        self::assertSame(1, $impact->affectedRowCount(AffectedRowsMode::Matched));
        self::assertFalse($impact->isInsertLike());
    }

    public function testReturningUpdateRowsExcludeInternalIdentityMetadata(): void
    {
        $impact = new MutationImpact(
            new UpdateMutation('items', ['id']),
            [['id' => 1, 'name' => 'before']],
            [['id' => 2, 'name' => 'after', '__ztd_original_id' => 1]],
            [['id' => 2, 'name' => 'after']],
        );

        self::assertSame([['id' => 2, 'name' => 'after']], $impact->returningRows());
    }

    public function testReturningDeleteRowsComeFromMutationInput(): void
    {
        $deleted = ['id' => 2, 'name' => 'deleted', '__ztd_original_id' => 2];
        $impact = new MutationImpact(
            new DeleteMutation('items', ['id']),
            [['id' => 1], ['id' => 2, 'name' => 'deleted']],
            [$deleted],
            [['id' => 1]],
        );

        self::assertSame([['id' => 2, 'name' => 'deleted']], $impact->returningRows());
        self::assertFalse($impact->isInsertLike());
    }

    public function testIgnoredInsertHasNoReturningRows(): void
    {
        $row = ['id' => 1];
        $impact = new MutationImpact(new InsertMutation('items'), [$row], [$row], [$row]);

        self::assertSame([], $impact->returningRows());
    }

    public function testUpsertFallsBackToInputAndIsInsertLike(): void
    {
        $row = ['id' => 1, 'name' => 'updated'];
        $impact = new MutationImpact(new UpsertMutation('items', ['id']), [$row], [$row], [$row]);

        self::assertSame([$row], $impact->returningRows());
        self::assertTrue($impact->isInsertLike());
    }

    public function testReplaceIsInsertLike(): void
    {
        $before = [['id' => 1, 'name' => 'before']];
        $after = [['id' => 1]];
        $impact = new MutationImpact(new ReplaceMutation('items', ['id']), $before, $after, $after);

        self::assertTrue($impact->isInsertLike());
        self::assertSame($after, $impact->returningRows());
    }

    public function testImpactPreservesEveryChangedRowAndDuplicateMultiplicity(): void
    {
        $first = ['id' => 1];
        $second = ['id' => 2];
        $impact = new MutationImpact(new InsertMutation('items'), [], [$first, $second], [$first, $second]);
        self::assertSame(2, $impact->affectedRowCount(AffectedRowsMode::Changed));
        self::assertSame([$first, $second], $impact->returningRows());

        $duplicateImpact = new MutationImpact(
            new DeleteMutation('items', ['id']),
            [$first, $first],
            [$first],
            [$first],
        );
        self::assertSame(1, $duplicateImpact->affectedRowCount(AffectedRowsMode::Changed));
    }

    public function testRowsWithDifferentColumnsOrValuesAreChanged(): void
    {
        $impact = new MutationImpact(
            new UpdateMutation('items', ['id']),
            [['id' => 1, 'name' => 'before'], ['id' => 2], ['id' => 3]],
            [],
            [['id' => 1, 'other' => 'before'], ['id' => 2, 'name' => null], ['id' => 4]],
        );

        self::assertSame(3, $impact->affectedRowCount(AffectedRowsMode::Changed));
    }

    public function testSkippedConditionalUpsertHasNoAffectedOrReturningRows(): void
    {
        $row = ['id' => 1, 'name' => 'original', 'score' => 50];
        $mutation = new UpsertMutation(
            'items',
            ['id'],
            ['name'],
            ['name' => 'EXCLUDED.name'],
            updatePredicate: 'score >= 80',
        );
        $store = new \ZtdQuery\Shadow\ShadowStore();
        $store->set('items', [$row]);
        $input = [['id' => 1, 'name' => 'skipped', 'score' => 95]];
        $mutation->apply($store, $input);
        $impact = new MutationImpact($mutation, [$row], $input, $store->get('items'));

        self::assertSame(0, $impact->affectedRowCount(AffectedRowsMode::Matched));
        self::assertSame([], $impact->returningRows());
    }
}
