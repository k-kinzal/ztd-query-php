<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\AffectedRowsMode;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\InsertMutation;
use ZtdQuery\Shadow\Mutation\MutationImpact;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\UpdateMutation;

#[CoversClass(MutationImpact::class)]
#[UsesClass(DeleteMutation::class)]
#[UsesClass(InsertMutation::class)]
#[UsesClass(MutationRowIdentity::class)]
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
}
