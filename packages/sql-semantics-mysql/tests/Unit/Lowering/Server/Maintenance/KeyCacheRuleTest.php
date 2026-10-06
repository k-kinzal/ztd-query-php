<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\KeyCacheRule;

#[CoversClass(KeyCacheRule::class)]
#[Medium]
final class KeyCacheRuleTest extends TestCase
{
    public function testStatementLowersBothStatements(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('load index into cache t')->toString());
    }

    public function testEntriesFlattensTheList(): void
    {
        self::assertSame('CACHE INDEX a, b, c IN k', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('cache index a, b, c in k')->toString());
    }

    public function testCachedLowersThePartitionForm(): void
    {
        self::assertSame('CACHE INDEX t PARTITION (p, q) IN k', (new Semantics(Dialect::MySql))->analyze('cache index t partition (p, q) in k')->toString());
    }

    public function testPreloadedLowersThePartitionForm(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t PARTITION (p) INDEX (i) IGNORE LEAVES', (new Semantics(Dialect::MySql))->analyze('load index into cache t partition (p) index (i) ignore leaves')->toString());
    }

    public function testEntryLowersTheIndexList(): void
    {
        self::assertSame('CACHE INDEX t INDEX (i, j) IN k', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('cache index t key (i, j) in k')->toString());
    }

    public function testPartitionsLowersAll(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t PARTITION (ALL)', (new Semantics(Dialect::MySql))->analyze('load index into cache t partition (all)')->toString());
    }

    public function testMarkedLowersThe5xPartitionSelection(): void
    {
        self::assertSame('CACHE INDEX t PARTITION (ALL) IN k', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('cache index t partition (all) in k')->toString());
    }

    public function testIndexesLowersAnEmptyList(): void
    {
        self::assertSame('CACHE INDEX t INDEX () IN k', (new Semantics(Dialect::MySql))->analyze('cache index t index () in k')->toString());
    }

    public function testKeysLowersPrimary(): void
    {
        self::assertSame('CACHE INDEX t INDEX (PRIMARY) IN k', (new Semantics(Dialect::MySql))->analyze('cache index t index (primary) in k')->toString());
    }

    public function testLeavesLowersIgnoreLeaves(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t IGNORE LEAVES', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('load index into cache t ignore leaves')->toString());
    }

    public function testCacheLowersDefault(): void
    {
        self::assertSame('CACHE INDEX t IN DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('cache index t in default')->toString());
    }
}
