<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\Key\ReferentialAction;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\CascadedChildren;
use ZtdQuery\Shadow\FollowedConstraint;
use ZtdQuery\Shadow\ForeignKeyCascade;
use ZtdQuery\Shadow\ForeignKeyEnds;
use ZtdQuery\Shadow\ParentKeyLookup;
use ZtdQuery\Shadow\Row\RowChange;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\TableTransition;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ForeignKeyCascade::class)]
#[UsesClass(CascadedChildren::class)]
#[UsesClass(FollowedConstraint::class)]
#[UsesClass(ForeignKeyEnds::class)]
#[UsesClass(ParentKeyLookup::class)]
#[UsesClass(RowMatch::class)]
#[UsesClass(RowChange::class)]
#[UsesClass(TableTransition::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ForeignKeyViolationException::class)]
final class ForeignKeyCascadeTest extends TestCase
{
    public function testOfDeletesTheChildrenOfADeletedParentWhereTheActionSaysToCascade(): void
    {
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id'], ReferentialAction::Cascade);

        $child = $cascade->of(
            $store,
            'children',
            'fk',
            $foreignKey,
            new TableTransition('parents', [['id' => 1]], []),
            'DELETE',
        );

        self::assertNotNull($child);
        self::assertSame([['id' => 10, 'parent_id' => 1]], $child->deleted);
        self::assertSame([], $store->get('children'));
    }

    public function testOfCarriesTheNewKeyToTheChildrenOfAnUpdatedParent(): void
    {
        $store = new ShadowStore();
        $store->set('parents', [['id' => 2]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(
            ['parent_id'],
            'parents',
            ['id'],
            onUpdate: ReferentialAction::Cascade,
        );

        $child = $cascade->of(
            $store,
            'children',
            'fk',
            $foreignKey,
            new TableTransition('parents', [], [new RowChange(['id' => 1], ['id' => 2])]),
            'UPDATE',
        );

        self::assertNotNull($child);
        self::assertSame([['id' => 10, 'parent_id' => 2]], $store->get('children'));
    }

    public function testOfLeavesTheChildrenAloneWhileAnotherParentRowHoldsTheKeyUp(): void
    {
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id'], ReferentialAction::Cascade);

        $child = $cascade->of(
            $store,
            'children',
            'fk',
            $foreignKey,
            new TableTransition('parents', [['id' => 1]], []),
            'DELETE',
        );

        self::assertNull($child);
        self::assertSame([['id' => 10, 'parent_id' => 1]], $store->get('children'));
    }

    public function testOfFollowsNothingThroughAKeyWhoseEndsDisagree(): void
    {
        $store = new ShadowStore();
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['a', 'b'], ReferentialAction::Cascade);

        $child = $cascade->of(
            $store,
            'children',
            'fk',
            $foreignKey,
            new TableTransition('parents', [['id' => 1]], []),
            'DELETE',
        );

        self::assertNull($child);
    }

    public function testApplyActionSetsTheKeyToNullWhereTheActionSaysTo(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);

        $row = $cascade->applyAction(
            ['id' => 10, 'parent_id' => 1],
            [],
            ReferentialAction::SetNull,
            new FollowedConstraint('children', 'fk', $foreignKey, 'DELETE'),
        );

        self::assertSame(['id' => 10, 'parent_id' => null], $row);
    }

    public function testApplyActionCarriesTheNewValueWhereTheActionSaysToCascade(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);

        $row = $cascade->applyAction(
            ['id' => 10, 'parent_id' => 1],
            [2],
            ReferentialAction::Cascade,
            new FollowedConstraint('children', 'fk', $foreignKey, 'UPDATE'),
        );

        self::assertSame(['id' => 10, 'parent_id' => 2], $row);
    }

    public function testApplyActionRefusesTheStatementWhereTheActionForbidsIt(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);

        $this->expectException(ForeignKeyViolationException::class);

        $cascade->applyAction(
            ['id' => 10, 'parent_id' => 1],
            [],
            ReferentialAction::Restrict,
            new FollowedConstraint('children', 'fk', $foreignKey, 'DELETE'),
        );
    }

    public function testCarryUpdateWritesTheNewKeyIntoEveryChildThatWasHoldingTheOldOne(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(
            ['parent_id'],
            'parents',
            ['id'],
            onUpdate: ReferentialAction::Cascade,
        );
        $constraint = new FollowedConstraint('children', 'fk', $foreignKey, 'UPDATE');
        $store = new ShadowStore();
        $store->set('parents', [['id' => 2]]);
        $children = new CascadedChildren([['id' => 10, 'parent_id' => 1], ['id' => 11, 'parent_id' => 9]]);

        $cascade->carryUpdate($children, $store, $constraint, ['id'], new RowChange(['id' => 1], ['id' => 2]));

        self::assertSame(
            [['id' => 10, 'parent_id' => 2], ['id' => 11, 'parent_id' => 9]],
            $children->rows(),
        );
    }

    public function testCarryUpdateReachesNothingWhereTheParentTableStillHoldsTheOldKey(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);
        $constraint = new FollowedConstraint('children', 'fk', $foreignKey, 'UPDATE');
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1], ['id' => 2]]);
        $children = new CascadedChildren([['id' => 10, 'parent_id' => 1]]);

        $cascade->carryUpdate($children, $store, $constraint, ['id'], new RowChange(['id' => 1], ['id' => 2]));

        self::assertTrue($children->areUnchanged());
    }

    public function testCarryDeleteDropsEveryChildThatWasHoldingTheKeyWhereTheActionCascades(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(
            ['parent_id'],
            'parents',
            ['id'],
            onDelete: ReferentialAction::Cascade,
        );
        $constraint = new FollowedConstraint('children', 'fk', $foreignKey, 'DELETE');
        $store = new ShadowStore();
        $store->set('parents', []);
        $children = new CascadedChildren([['id' => 10, 'parent_id' => 1], ['id' => 11, 'parent_id' => 9]]);

        $cascade->carryDelete($children, $store, $constraint, ['id'], ['id' => 1]);

        self::assertSame(
            [[['id' => 11, 'parent_id' => 9]], [['id' => 10, 'parent_id' => 1]]],
            [$children->rows(), $children->deleted()],
        );
    }

    public function testCarryDeleteSetsTheKeyToNullWhereTheActionSaysToRatherThanDroppingTheRow(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(
            ['parent_id'],
            'parents',
            ['id'],
            onDelete: ReferentialAction::SetNull,
        );
        $constraint = new FollowedConstraint('children', 'fk', $foreignKey, 'DELETE');
        $store = new ShadowStore();
        $store->set('parents', []);
        $children = new CascadedChildren([['id' => 10, 'parent_id' => 1]]);

        $cascade->carryDelete($children, $store, $constraint, ['id'], ['id' => 1]);

        self::assertSame([['id' => 10, 'parent_id' => null]], $children->rows());
    }

    public function testCarryDeleteReachesNothingWhereTheParentTableStillHoldsTheKey(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);
        $constraint = new FollowedConstraint('children', 'fk', $foreignKey, 'DELETE');
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $children = new CascadedChildren([['id' => 10, 'parent_id' => 1]]);

        $cascade->carryDelete($children, $store, $constraint, ['id'], ['id' => 1]);

        self::assertTrue($children->areUnchanged());
    }
}
