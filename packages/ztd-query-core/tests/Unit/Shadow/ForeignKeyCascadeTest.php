<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\ForeignKeyDefinition;
use ZtdQuery\Schema\ReferentialAction;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ForeignKeyCascade;
use ZtdQuery\Shadow\ForeignKeyEnds;
use ZtdQuery\Shadow\ParentKeyLookup;
use ZtdQuery\Shadow\Row\RowChange;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\Row\TableTransition;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ForeignKeyCascade::class)]
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
            ['parent_id'],
            [],
            ReferentialAction::SetNull,
            'children',
            'fk',
            $foreignKey,
            'DELETE',
        );

        self::assertSame(['id' => 10, 'parent_id' => null], $row);
    }

    public function testApplyActionCarriesTheNewValueWhereTheActionSaysToCascade(): void
    {
        $cascade = new ForeignKeyCascade(new ForeignKeyEnds(new TableDefinitionRegistry()));
        $foreignKey = new ForeignKeyDefinition(['parent_id'], 'parents', ['id']);

        $row = $cascade->applyAction(
            ['id' => 10, 'parent_id' => 1],
            ['parent_id'],
            [2],
            ReferentialAction::Cascade,
            'children',
            'fk',
            $foreignKey,
            'UPDATE',
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
            ['parent_id'],
            [],
            ReferentialAction::Restrict,
            'children',
            'fk',
            $foreignKey,
            'DELETE',
        );
    }
}
