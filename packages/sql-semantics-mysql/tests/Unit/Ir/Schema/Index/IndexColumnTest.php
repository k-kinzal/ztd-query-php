<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexColumn;

#[CoversClass(IndexColumn::class)]
#[Small]
final class IndexColumnTest extends TestCase
{
    public function testRetainsTheLastNativeFieldAndItsDirection(): void
    {
        $column = new IndexColumn(1016, true);

        self::assertSame(1016, $column->slot);
        self::assertTrue($column->descending);
    }
}
