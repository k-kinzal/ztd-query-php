<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Schema\Key\ForeignKeyDefinition;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ForeignKeyEnds;
use ZtdQuery\Shadow\ForeignKeyIntegrity;
use ZtdQuery\Shadow\ParentKeyLookup;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ForeignKeyIntegrity::class)]
#[UsesClass(ForeignKeyEnds::class)]
#[UsesClass(ParentKeyLookup::class)]
#[UsesClass(RowMatch::class)]
#[UsesClass(ShadowStore::class)]
#[UsesClass(ForeignKeyDefinition::class)]
#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ForeignKeyViolationException::class)]
final class ForeignKeyIntegrityTest extends TestCase
{
    public function testAssertHoldsPassesAShadowInWhichEveryChildFindsItsParent(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('children', new TableDefinition(['id', 'parent_id'], [], ['id'], [], [], foreignKeys: [
            'fk' => new ForeignKeyDefinition(['parent_id'], 'parents', ['id']),
        ]));
        $store = new ShadowStore();
        $store->set('parents', [['id' => 1]]);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);

        (new ForeignKeyIntegrity($registry, new ForeignKeyEnds($registry)))->assertHolds($store, 'INSERT');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertHoldsRefusesAShadowInWhichAChildReferencesNothing(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('children', new TableDefinition(['id', 'parent_id'], [], ['id'], [], [], foreignKeys: [
            'fk' => new ForeignKeyDefinition(['parent_id'], 'parents', ['id']),
        ]));
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);

        $this->expectException(ForeignKeyViolationException::class);

        (new ForeignKeyIntegrity($registry, new ForeignKeyEnds($registry)))->assertHolds($store, 'INSERT');
    }

    public function testAssertHoldsLeavesAKeyWithANullInItAlone(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('children', new TableDefinition(['id', 'parent_id'], [], ['id'], [], [], foreignKeys: [
            'fk' => new ForeignKeyDefinition(['parent_id'], 'parents', ['id']),
        ]));
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 10, 'parent_id' => null]]);

        (new ForeignKeyIntegrity($registry, new ForeignKeyEnds($registry)))->assertHolds($store, 'INSERT');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertHoldsChecksNothingThroughAKeyWhoseEndsDisagree(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('children', new TableDefinition(['id', 'parent_id'], [], ['id'], [], [], foreignKeys: [
            'fk' => new ForeignKeyDefinition(['parent_id'], 'parents', ['a', 'b']),
        ]));
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 10, 'parent_id' => 1]]);

        (new ForeignKeyIntegrity($registry, new ForeignKeyEnds($registry)))->assertHolds($store, 'INSERT');

        $this->expectNotToPerformAssertions();
    }

    public function testAssertHoldsLeavesARowThatDoesNotCarryTheKeyColumnsAlone(): void
    {
        $registry = new TableDefinitionRegistry();
        $registry->register('children', new TableDefinition(['id', 'parent_id'], [], ['id'], [], [], foreignKeys: [
            'fk' => new ForeignKeyDefinition(['parent_id'], 'parents', ['id']),
        ]));
        $store = new ShadowStore();
        $store->set('parents', []);
        $store->set('children', [['id' => 10]]);

        (new ForeignKeyIntegrity($registry, new ForeignKeyEnds($registry)))->assertHolds($store, 'INSERT');

        $this->expectNotToPerformAssertions();
    }
}
