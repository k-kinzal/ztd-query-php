<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(MaintainedTable::class)]
#[Medium]
final class MaintainedTableTest extends TestCase
{
    public function testRenderWritesTheQualifiedName(): void
    {
        self::assertSame('OPTIMIZE TABLE db.t', (new Inspection())->inspect('optimize table db.t')->toString());
    }
}
