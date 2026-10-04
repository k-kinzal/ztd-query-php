<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\KeyCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\PreloadedTable;

#[CoversClass(PreloadedTable::class)]
#[Medium]
final class PreloadedTableTest extends TestCase
{
    public function testRenderWritesIgnoreLeaves(): void
    {
        self::assertSame('LOAD INDEX INTO CACHE t INDEX (PRIMARY) IGNORE LEAVES', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('load index into cache t key (primary) ignore leaves')->toString());
    }
}
