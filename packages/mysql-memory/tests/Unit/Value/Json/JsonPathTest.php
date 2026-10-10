<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonLegKind;
use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonPath::class)]
#[Small]
final class JsonPathTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerParseRefusesAnInvalidPathAtThePositionTheServerNames(): iterable
    {
        yield 'empty' => ['', 0];
        yield 'no scope' => ['bad', 1];
        yield 'leg without dot' => ['$a', 1];
        yield 'missing name' => ['$.', 2];
        yield 'name not an identifier' => ['$.a-b', 5];
        yield 'unterminated quoted name' => ['$."a', 4];
        yield 'unclosed cell' => ['$[0', 3];
        yield 'character before the bracket' => ['$[1.5]', 4];
        yield 'negative index' => ['$[-1]', 2];
        yield 'index too big' => ['$[4294967296]', 2];
        yield 'range backwards' => ['$[2 to 1]', 8];
        yield 'to without space' => ['$[0 to1]', 5];
        yield 'last minus nothing' => ['$[last - ]', 9];
        yield 'lone star' => ['$*', 2];
        yield 'two stars at the end' => ['$**', 3];
        yield 'three stars' => ['$***', 3];
        yield 'two stars after a name' => ['$.a**', 5];
        yield 'trailing character' => ['$[0]x', 4];
    }

    #[DataProvider('providerParseRefusesAnInvalidPathAtThePositionTheServerNames')]
    public function testParseRefusesAnInvalidPathAtThePositionTheServerNames(string $path, int $position): void
    {
        $this->expectExceptionObject(new JsonSyntax(JsonPath::INVALID, $position));

        JsonPath::parse($path);
    }

    public function testParseReadsEveryKindOfLeg(): void
    {
        $path = JsonPath::parse(' $ .a ."b c" .* [ 1 ] [last - 2] [*] [0 to last] **.d ');

        self::assertSame([JsonLegKind::Member, JsonLegKind::Member, JsonLegKind::AnyMember, JsonLegKind::Cell, JsonLegKind::Cell, JsonLegKind::AnyCell, JsonLegKind::Range, JsonLegKind::Descendants, JsonLegKind::Member], array_map(static fn ($leg): JsonLegKind => $leg->kind, $path->legs));
        self::assertSame(['a', 'b c', [true, 2], [true, 0]], [$path->legs[0]->name, $path->legs[1]->name, $path->legs[4]->from, $path->legs[6]->to]);
    }

    public function testMemberReadsANameQuotedOrNot(): void
    {
        $at = 0;
        $quoted = JsonPath::member('."a\"b".c', $at);
        $plain = JsonPath::member('."a\"b".c', $at);

        self::assertSame(['a"b', 'c', 9], [$quoted->name, $plain->name, $at]);
    }

    public function testCellReadsACellARangeOrEveryCell(): void
    {
        $at = 0;
        $range = JsonPath::cell('[1 to 2][*]', $at);
        $every = JsonPath::cell('[1 to 2][*]', $at);

        self::assertSame([JsonLegKind::Range, [false, 1], [false, 2], JsonLegKind::AnyCell, 11], [$range->kind, $range->from, $range->to, $every->kind, $at]);
    }

    public function testIndexReadsANumberOrLast(): void
    {
        $at = 0;
        $last = JsonPath::index('last-3]', $at);
        $position = $at;
        $at = 0;

        self::assertSame([[true, 3], 6, [false, 7], 2], [$last, $position, JsonPath::index('07', $at), $at]);
    }

    public function testCloseReadsTheClosingBracket(): void
    {
        $at = 1;
        JsonPath::close(' ]', $at);

        self::assertSame(2, $at);
    }

    public function testDescendantsReadsTwoStarsBeforeALeg(): void
    {
        $at = 0;

        self::assertSame([JsonLegKind::Descendants, 2], [JsonPath::descendants('**.a', $at)->kind, $at]);
    }

    public function testWildHoldsForAPathThatCanSelectSeveralValues(): void
    {
        self::assertSame([false, true, true], [JsonPath::parse('$.a[0]')->wild(), JsonPath::parse('$.*')->wild(), JsonPath::parse('$[0 to 1]')->wild()]);
    }

    public function testSelectAnswersEachValueOnce(): void
    {
        self::assertSame(['1', '2', '3'], array_map(static fn (JsonNode $node): string => $node->text(), JsonPath::parse('$**[0]')->select(JsonNode::parse('[1, [2, [3]]]'))));
    }

    public function testExtractAnswersAValueAnArrayOrNull(): void
    {
        $document = JsonNode::parse('{"a": [1, 2, {"a": 3}], "a b": 4}');

        self::assertSame(['[1, 2, {"a": 3}]', '[[1, 2, {"a": 3}], 3]', '4', null, '[{"a": [1, 2, {"a": 3}], "a b": 4}]'], [JsonPath::parse('$.a')->extract($document)?->text(), JsonPath::parse('$**.a')->extract($document)?->text(), JsonPath::parse('$."a b"[last]')->extract($document)?->text(), JsonPath::parse('$.a[3]')->extract($document)?->text(), JsonPath::parse('$[0 to 9]')->extract($document)?->text()]);
    }
}
