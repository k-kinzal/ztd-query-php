<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Table\ActionRows;
use SqlParser\Table\ArrayRows;

#[CoversClass(ActionRows::class)]
#[UsesClass(ArrayRows::class)]
#[Small]
final class ActionRowsTest extends TestCase
{
    public function testRow(): void
    {
        $rows = new ArrayRows([[1 => 2]]);

        self::assertSame([1 => 2], $rows->row(0));
    }

    public function testCount(): void
    {
        self::assertSame(1, (new ArrayRows([[1 => 2]]))->count());
    }
}
