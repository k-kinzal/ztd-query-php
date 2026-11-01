<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonMerge;
use MySqlMemory\Value\Json\JsonNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonMerge::class)]
#[Small]
final class JsonMergeTest extends TestCase
{
    public function testPreserveKeepsEveryValue(): void
    {
        self::assertSame(['{"a": [1, 2], "b": 3}', '[1, {"a": 1}]', '[1, 2]'], [JsonMerge::preserve(JsonNode::parse('{"a": 1}'), JsonNode::parse('{"a": [2], "b": 3}'))->text(), JsonMerge::preserve(JsonNode::parse('[1]'), JsonNode::parse('{"a": 1}'))->text(), JsonMerge::preserve(JsonNode::parse('1'), JsonNode::parse('2'))->text()]);
    }

    public function testPatchReplacesAndRemovesMembers(): void
    {
        self::assertSame(['{"b": 2, "c": {}}', '[2]', '{"b": 2, "c": 3}'], [JsonMerge::patch(JsonNode::parse('{"a": 1, "b": 2}'), JsonNode::parse('{"a": null, "c": {"d": null}}'))->text(), JsonMerge::patch(JsonNode::parse('{"a": 1}'), JsonNode::parse('[2]'))->text(), JsonMerge::patch(JsonNode::parse('{"a": 1, "b": 2}'), JsonNode::parse('{"a": null, "c": 3}'))->text()]);
    }
}
