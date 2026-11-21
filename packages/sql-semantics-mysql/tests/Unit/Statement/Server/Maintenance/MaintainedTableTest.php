<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;

#[CoversClass(MaintainedTable::class)]
#[Medium]
final class MaintainedTableTest extends TestCase
{
    public function testRenderWritesTheQualifiedName(): void
    {
        self::assertSame('OPTIMIZE TABLE db.t', (new Semantics())->analyze('optimize table db.t')->toString());
    }
}
