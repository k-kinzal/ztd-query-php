<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanColumn;

#[CoversClass(IndexBooleanColumn::class)]
#[Small]
final class IndexBooleanColumnTest extends TestCase
{
    public function testRetainsThePhysicalColumnValueSlot(): void
    {
        self::assertSame(3, (new IndexBooleanColumn(3))->slot);
    }
}
