<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\DeleteMutation;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\UpdateMutation;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\TableTransitions;

#[CoversClass(TableTransitions::class)]
#[UsesClass(RowMatch::class)]
#[UsesClass(MutationRowIdentity::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(DeleteMutation::class)]
#[UsesClass(UpdateMutation::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\TableTransition::class)]
#[UsesClass(\ZtdQuery\Shadow\Row\RowChange::class)]
#[UsesClass(\ZtdQuery\Shadow\Mutation\RowConstraints::class)]
final class TableTransitionsTest extends TestCase
{
    public function testBetweenReadsARowThatIsNoLongerThereAsDeleted(): void
    {
        $transition = (new TableTransitions(new TableDefinitionRegistry()))
            ->between('order', [['id' => 1], ['id' => 2]], [['id' => 1]], ['id'], []);

        self::assertSame([['id' => 2]], $transition->deleted);
        self::assertSame([], $transition->updated);
    }

    public function testBetweenReadsARowThatChangedAsUpdated(): void
    {
        $transition = (new TableTransitions(new TableDefinitionRegistry()))
            ->between('order', [['id' => 1, 'total' => 1]], [['id' => 1, 'total' => 2]], ['id'], []);

        self::assertSame([], $transition->deleted);
        self::assertCount(1, $transition->updated);
        self::assertSame(['id' => 1, 'total' => 2], $transition->updated[0]->after);
    }

    public function testBetweenPairsARowByTheOldKeyTheResultCarries(): void
    {
        $transition = (new TableTransitions(new TableDefinitionRegistry()))->between(
            'order',
            [['id' => 1]],
            [['id' => 2]],
            ['id'],
            [['id' => 2, '__ztd_original_id' => 1]],
        );

        self::assertSame([], $transition->deleted);
        self::assertSame(['id' => 1], $transition->updated[0]->before);
        self::assertSame(['id' => 2], $transition->updated[0]->after);
    }

    public function testBetweenPairsOnlyIdenticalRowsWhereTheTableDeclaresNoKey(): void
    {
        $transition = (new TableTransitions(new TableDefinitionRegistry()))
            ->between('log', [['note' => 'a'], ['note' => 'b']], [['note' => 'a']], [], []);

        self::assertSame([['note' => 'b']], $transition->deleted);
        self::assertSame([], $transition->updated);
    }

    public function testBetweenPairsTwoRowsSharingAKeyOneToOne(): void
    {
        $transition = (new TableTransitions(new TableDefinitionRegistry()))
            ->between('order', [['id' => 1], ['id' => 1]], [['id' => 1]], ['id'], []);

        self::assertSame([['id' => 1]], $transition->deleted);
    }

    public function testUnmatchedAnswersTheRowsNothingWasPairedWith(): void
    {
        $rows = [['id' => 1], ['id' => 2], ['id' => 3]];

        self::assertSame(
            [['id' => 2]],
            (new TableTransitions(new TableDefinitionRegistry()))->unmatched($rows, [0, 2]),
        );
    }

    public function testOfLeavesOutATableNothingHappenedTo(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));
        $before = new ShadowStore();
        $before->set('order', [['id' => 1]]);
        $after = $before->snapshot();

        $transitions = (new TableTransitions($registry))
            ->of($before, $after, new DeleteMutation('order', ['id']), []);

        self::assertSame([], $transitions);
    }

    public function testOfAnswersOneEntryPerTableSomethingHappenedTo(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));
        $before = new ShadowStore();
        $before->set('order', [['id' => 1]]);
        $after = $before->snapshot();
        $after->set('order', []);

        $transitions = (new TableTransitions($registry))
            ->of($before, $after, new DeleteMutation('order', ['id']), []);

        self::assertCount(1, $transitions);
        self::assertSame('order', $transitions[0]->table);
        self::assertSame([['id' => 1]], $transitions[0]->deleted);
    }

    public function testOfReadsTheResultRowsOnlyForTheTableAnUpdateNames(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('order', new TableDefinition(['id'], [], ['id'], [], []));
        $before = new ShadowStore();
        $before->set('order', [['id' => 1]]);
        $after = $before->snapshot();
        $after->set('order', [['id' => 2]]);

        $transitions = (new TableTransitions($registry))->of(
            $before,
            $after,
            new UpdateMutation('order', ['id']),
            [['id' => 2, '__ztd_original_id' => 1]],
        );

        self::assertSame([], $transitions[0]->deleted);
        self::assertCount(1, $transitions[0]->updated);
    }
}
