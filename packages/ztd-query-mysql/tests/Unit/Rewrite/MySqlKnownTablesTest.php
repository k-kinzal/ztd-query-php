<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parse\MySqlSelectRelationParser;
use ZtdQuery\Platform\MySql\Rewrite\MySqlCteShadowComposer;
use ZtdQuery\Platform\MySql\Rewrite\MySqlKnownTables;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Schema\ViewDefinitionSet;
use ZtdQuery\Shadow\ShadowStore;

#[CoversClass(MySqlKnownTables::class)]
#[UsesClass(MySqlSelectRelationParser::class)]
#[UsesClass(MySqlCteShadowComposer::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[UsesClass(ViewDefinitionSet::class)]
#[UsesClass(ShadowStore::class)]
final class MySqlKnownTablesTest extends TestCase
{
    public function testFirstUnknownInAnswersTheFirstTableTheShadowCannotSpeakFor(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $known = new MySqlKnownTables($store, new TableDefinitionRegistry(), new ViewDefinitionSet());

        self::assertSame('orders', $known->firstUnknownIn('SELECT * FROM users JOIN orders ON 1 = 1'));
    }

    public function testFirstUnknownInAnswersNothingWhereTheShadowKnowsThemAll(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $known = new MySqlKnownTables($store, new TableDefinitionRegistry(), new ViewDefinitionSet());

        self::assertNull($known->firstUnknownIn('SELECT * FROM users'));
    }

    public function testFirstUnknownInPassesOverANameTheStatementDeclaresForItself(): void
    {
        $known = new MySqlKnownTables(new ShadowStore(), new TableDefinitionRegistry(), new ViewDefinitionSet());

        self::assertNull($known->firstUnknownIn('WITH recent AS (SELECT 1 AS id) SELECT * FROM recent'));
    }

    public function testKnowsATableTheShadowHoldsRowsFor(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);
        $known = new MySqlKnownTables($store, new TableDefinitionRegistry(), new ViewDefinitionSet());

        self::assertSame([true, false], [$known->knows('users'), $known->knows('orders')]);
    }

    public function testHasAnythingSaysWhetherTheShadowHasBeenToldAnythingAtAll(): void
    {
        $store = new ShadowStore();
        $store->set('users', []);

        self::assertSame(
            [false, true],
            [
                (new MySqlKnownTables(new ShadowStore(), new TableDefinitionRegistry(), new ViewDefinitionSet()))->hasAnything(),
                (new MySqlKnownTables($store, new TableDefinitionRegistry(), new ViewDefinitionSet()))->hasAnything(),
            ],
        );
    }
}
