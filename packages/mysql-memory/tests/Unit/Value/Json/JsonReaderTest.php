<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonKind;
use MySqlMemory\Value\Json\JsonReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonReader::class)]
#[Small]
final class JsonReaderTest extends TestCase
{
    public function testReadMovesThePositionPastTheValue(): void
    {
        $at = 1;
        $node = JsonReader::read('[-1.5e-7, 3]', $at);

        self::assertSame([JsonKind::Double, 8], [$node->type, $at]);
    }

    public function testReadKeepsTheTypeOfAMarkedValue(): void
    {
        $at = 0;
        $node = JsonReader::read('[`d1.50, `D"2024-01-31", true, null]', $at);

        self::assertSame(['[`d1.50, `D"2024-01-31", true, null]', 36], [$node->typed(), $at]);
    }

    public function testContainerReadsTheMembersInOrder(): void
    {
        $at = 0;
        $node = JsonReader::container('{"a": [1, 2], "bc": {}}, 3', $at);

        self::assertSame([JsonKind::Object, '{"a": [1, 2], "bc": {}}', 23], [$node->type, $node->text(), $at]);
    }

    public function testNumberTellsDoublesIntegersAndUnsignedIntegersApart(): void
    {
        $at = 0;
        $double = JsonReader::number('1e2', $at);
        $at = 0;
        $integer = JsonReader::number('-9223372036854775808', $at);
        $at = 0;
        $unsigned = JsonReader::number('9223372036854775808]', $at);

        self::assertSame([JsonKind::Double, JsonKind::Integer, JsonKind::Unsigned, 19], [$double->type, $integer->type, $unsigned->type, $at]);
    }

    public function testCharactersUndoesTheEscapesTheServerWrites(): void
    {
        $at = 0;

        self::assertSame(["a\"\\\x08\x0c\n\r\t\x01/", 24], [JsonReader::characters('"a\"\\\\\b\f\n\r\t\u0001/"', $at), $at]);
    }
}
