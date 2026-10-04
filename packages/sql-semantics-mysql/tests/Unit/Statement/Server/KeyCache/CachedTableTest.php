<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\KeyCache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;

#[CoversClass(CachedTable::class)]
#[Medium]
final class CachedTableTest extends TestCase
{
    public function testRenderWritesPartitionsAndIndexes(): void
    {
        self::assertSame('CACHE INDEX t PARTITION (p) INDEX () IN c', (new Semantics(Dialect::MySql))->analyze('cache index t partition (p) key () in c')->toString());
    }
}
