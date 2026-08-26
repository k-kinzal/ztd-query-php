<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\ParentKeyLookup;
use ZtdQuery\Shadow\Row\RowMatch;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(ParentKeyLookup::class)]
#[UsesClass(RowMatch::class)]
#[UsesClass(ShadowStore::class)]
final class ParentKeyLookupTest extends TestCase
{
    public function testExistsWhenSomeRowStillCarriesTheValues(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1], ['id' => 2]]);

        self::assertTrue((new ParentKeyLookup())->exists($store, 'order', ['id'], [2]));
    }

    public function testDoesNotExistWhenNoRowCarriesTheValues(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['id' => 1]]);

        self::assertFalse((new ParentKeyLookup())->exists($store, 'order', ['id'], [2]));
    }

    public function testDoesNotExistWhenTheTableHasNoRowsAtAll(): void
    {
        self::assertFalse((new ParentKeyLookup())->exists(new ShadowStore(), 'order', ['id'], [1]));
    }

    public function testExistsOnlyWhenOneRowCarriesEveryColumnOfACompositeKey(): void
    {
        $store = new ShadowStore();
        $store->set('order', [['shop_id' => 1, 'no' => 9], ['shop_id' => 2, 'no' => 8]]);

        self::assertTrue((new ParentKeyLookup())->exists($store, 'order', ['shop_id', 'no'], [2, 8]));
        self::assertFalse((new ParentKeyLookup())->exists($store, 'order', ['shop_id', 'no'], [1, 8]));
    }
}
