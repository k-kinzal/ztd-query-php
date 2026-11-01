<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Plan\Path\Source\JsonColumn;
use MySqlMemory\Plan\Path\Source\JsonTableScan;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonTableScan::class)]
#[Small]
final class JsonTableScanTest extends TestCase
{
    public function testWidthCountsTheColumnsWithNestedColumnsFlattened(): void
    {
        $nested = new JsonColumn('nested', '', Domain::null(), JsonPath::parse('$[*]'), columns: [new JsonColumn('ordinality', 'm', Domain::integer()), new JsonColumn('path', 'v', Domain::integer(), JsonPath::parse('$'))]);

        self::assertSame(3, (new JsonTableScan(new Constant(Domain::null(), null), JsonPath::parse('$'), [new JsonColumn('ordinality', 'n', Domain::integer()), $nested]))->width());
    }
}
