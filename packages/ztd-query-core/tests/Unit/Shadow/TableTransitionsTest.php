<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\Mutation\MutationRowIdentity;
use ZtdQuery\Shadow\Mutation\Row\DeleteMutation;
use ZtdQuery\Shadow\Mutation\Row\UpdateMutation;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\RowPairing;
use ZtdQuery\Shadow\ShadowStore;
use ZtdQuery\Shadow\TableTransitions;

#[CoversClass(TableTransitions::class)]
#[UsesClass(RowMatch::class)]
#[UsesClass(RowPairing::class)]
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

    public function testPairIdenticalPairsOffOnlyRowsThatAreWhatTheyWere(): void
    {
        $transitions = new TableTransitions(new TableDefinitionRegistry());
        $pairing = new RowPairing();

        $transitions->pairIdentical($pairing, [['id' => 1], ['id' => 2]], [['id' => 2], ['id' => 3]]);

        self::assertSame([[1], [0], []], [
            $pairing->beforePositions(),
            $pairing->afterPositions(),
            $pairing->changes(),
        ]);
    }

    public function testPairByIdentityTakesThePairsAnUpdateToldUsAbout(): void
    {
        $transitions = new TableTransitions(new TableDefinitionRegistry());
        $pairing = new RowPairing();
        $identity = new MutationRowIdentity();

        $transitions->pairByIdentity(
            $pairing,
            [['id' => 1]],
            [['id' => 2]],
            ['id'],
            [['id' => 2, $identity->column('id') => 1]],
        );

        self::assertSame([[0], [0], 1], [
            $pairing->beforePositions(),
            $pairing->afterPositions(),
            count($pairing->changes()),
        ]);
    }

    public function testPairByIdentityLeavesARowNoResultRowSpeaksFor(): void
    {
        $transitions = new TableTransitions(new TableDefinitionRegistry());
        $pairing = new RowPairing();
        $identity = new MutationRowIdentity();

        $transitions->pairByIdentity(
            $pairing,
            [['id' => 1]],
            [['id' => 2]],
            ['id'],
            [['id' => 9, $identity->column('id') => 8]],
        );

        self::assertSame([], $pairing->beforePositions());
    }

    public function testPairByKeyPairsOffWhateverTheKeyStillMatches(): void
    {
        $transitions = new TableTransitions(new TableDefinitionRegistry());
        $pairing = new RowPairing();

        $transitions->pairByKey($pairing, [['id' => 1, 'a' => 1]], [['id' => 1, 'a' => 2]], ['id']);

        self::assertSame([[0], [0], 1], [
            $pairing->beforePositions(),
            $pairing->afterPositions(),
            count($pairing->changes()),
        ]);
    }

    public function testPairByKeyLeavesARowAlreadyPairedOff(): void
    {
        $transitions = new TableTransitions(new TableDefinitionRegistry());
        $pairing = new RowPairing();
        $pairing->pair(0, 0, ['id' => 1], ['id' => 1]);

        $transitions->pairByKey($pairing, [['id' => 1]], [['id' => 1]], ['id']);

        self::assertSame([0], $pairing->beforePositions());
    }

    public function testPairByKeyLeavesARowTheKeyNoLongerMatches(): void
    {
        $transitions = new TableTransitions(new TableDefinitionRegistry());
        $pairing = new RowPairing();

        $transitions->pairByKey($pairing, [['id' => 1]], [['id' => 2]], ['id']);

        self::assertSame([], $pairing->beforePositions());
    }
}
