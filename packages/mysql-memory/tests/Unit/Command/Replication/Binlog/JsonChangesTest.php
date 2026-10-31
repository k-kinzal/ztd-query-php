<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\JsonChanges;
use MySqlMemory\Command\Replication\Binlog\Reader;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonChanges::class)]
#[Small]
final class JsonChangesTest extends TestCase
{
    public function testApplyRunsAnOrderedReplacementAndRemoval(): void
    {
        $diff = "\0\x03$.a\x03\x05\x07\0\x02\x03$.b";
        self::assertSame('{"a": 7}', (new JsonChanges())->apply(new Reader(pack('V', strlen($diff)) . $diff), '{"a": 1, "b": 2}'));
    }

    public function testPathRejectsWildcardsInRuntimeDiffData(): void
    {
        $this->expectExceptionCode(1149);
        (new JsonChanges())->path('$.*');
    }

    public function testChangeInsertsBeforeAnArrayCell(): void
    {
        self::assertSame('[1, 7, 2]', (new JsonChanges())->change(JsonNode::parse('[1, 2]'), 1, JsonPath::parse('$[1]'), JsonNode::parse('7'))->text());
    }
}
