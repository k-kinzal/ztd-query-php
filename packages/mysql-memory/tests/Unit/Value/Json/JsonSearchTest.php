<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonNode;
use MySqlMemory\Value\Json\JsonSearch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSearch::class)]
#[Small]
final class JsonSearchTest extends TestCase
{
    public function testContainsFollowsTheContainmentRulesOfTheServer(): void
    {
        self::assertSame(
            [true, true, true, false, true, false, false, true],
            [
                JsonSearch::contains(JsonNode::parse('[1, 2, [3, 4]]'), JsonNode::parse('[1, 3]')),
                JsonSearch::contains(JsonNode::parse('[1, 2, [3, 4]]'), JsonNode::parse('[[3]]')),
                JsonSearch::contains(JsonNode::parse('{"a": [1, 2]}'), JsonNode::parse('{"a": 1}')),
                JsonSearch::contains(JsonNode::parse('{"a": {"x": 1}}'), JsonNode::parse('{"x": 1}')),
                JsonSearch::contains(JsonNode::parse('[{"a": 1, "b": 2}]'), JsonNode::parse('{"a": 1}')),
                JsonSearch::contains(JsonNode::parse('1'), JsonNode::parse('[1]')),
                JsonSearch::contains(JsonNode::parse('[]'), JsonNode::parse('{}')),
                JsonSearch::contains(JsonNode::parse('[1]'), JsonNode::parse('1.0')),
            ],
        );
    }

    public function testOverlapsFindsACommonElementOrMember(): void
    {
        self::assertSame(
            [true, true, false, false, true, false],
            [
                JsonSearch::overlaps(JsonNode::parse('[1, 2]'), JsonNode::parse('[2, 3]')),
                JsonSearch::overlaps(JsonNode::parse('{"a": 1, "b": 2}'), JsonNode::parse('{"b": 2}')),
                JsonSearch::overlaps(JsonNode::parse('{"a": 1}'), JsonNode::parse('{"a": 2}')),
                JsonSearch::overlaps(JsonNode::parse('[[1]]'), JsonNode::parse('[1]')),
                JsonSearch::overlaps(JsonNode::parse('{"a": 1}'), JsonNode::parse('[{"a": 1}]')),
                JsonSearch::overlaps(JsonNode::parse('[]'), JsonNode::parse('[]')),
            ],
        );
    }

    public function testPathsWritesThePathOfEachValue(): void
    {
        self::assertSame(['$', '$."1"', '$.c', '$.c[0]', '$."a b"'], array_values(JsonSearch::paths(JsonNode::parse('{"a b": 1, "1": 2, "c": [3]}'))));
    }

    public function testNameQuotesANameThatIsNoIdentifier(): void
    {
        self::assertSame(['abc', '"1"', '"a b"', 'é'], [JsonSearch::name('abc'), JsonSearch::name('1'), JsonSearch::name('a b'), JsonSearch::name('é')]);
    }
}
