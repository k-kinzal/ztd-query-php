<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPretty;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonPretty::class)]
#[Small]
final class JsonPrettyTest extends TestCase
{
    public function testTextWritesTheDocumentOverIndentedLines(): void
    {
        self::assertSame(
            ["{\n  \"a\": [\n    1,\n    {\n      \"b\": []\n    },\n    {}\n  ],\n  \"c\": \"x\\ny\"\n}", '1', "[\n  1.50\n]"],
            [JsonPretty::text(JsonNode::parse('{"a": [1, {"b": []}, {}], "c": "x\\ny"}')), JsonPretty::text(JsonNode::parse('1')), JsonPretty::text(new JsonNode(JsonKind::Array, [new JsonNode(JsonKind::Decimal, '1.50')]))],
        );
    }
}
