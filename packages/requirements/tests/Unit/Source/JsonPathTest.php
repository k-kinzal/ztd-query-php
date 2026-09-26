<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Source\JsonPath;
use RuntimeException;

#[CoversClass(JsonPath::class)]
#[Small]
final class JsonPathTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testSelectRootIsTheWholeDocument(): void
    {
        $document = json_decode('{"a":1}', false, 512, JSON_THROW_ON_ERROR);

        self::assertSame([['path' => '', 'value' => $document]], (new JsonPath())->select($document, '$'));
    }

    /**
     * @param list<array{path: string, value: mixed}> $expected
     * @throws JsonException
     */
    #[DataProvider('providerSelect')]
    public function testSelect(string $path, array $expected): void
    {
        $document = json_decode('{"rules":[{"text":"First."},{"text":"Second.","note":{"text":"Nested."}}],"a/b~c":"Escaped.","0":"Zero.","other":"Outside."}', true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($expected, (new JsonPath())->select($document, $path));
    }

    /**
     * @return array<string, array{string, list<array{path: string, value: mixed}>}>
     */
    public static function providerSelect(): array
    {
        return [
            'child name' => ['$.other', [['path' => '/other', 'value' => 'Outside.']]],
            'index' => ['$.rules[1].text', [['path' => '/rules/1/text', 'value' => 'Second.']]],
            'single-quoted key' => ["$['rules'][0]['text']", [['path' => '/rules/0/text', 'value' => 'First.']]],
            'double-quoted key' => ['$["other"]', [['path' => '/other', 'value' => 'Outside.']]],
            'escaped pointer' => ["$['a/b~c']", [['path' => '/a~1b~0c', 'value' => 'Escaped.']]],
            'numeric key' => ['$[0]', [['path' => '/0', 'value' => 'Zero.']]],
            'bracket wildcard' => ['$.rules[*].text', [['path' => '/rules/0/text', 'value' => 'First.'], ['path' => '/rules/1/text', 'value' => 'Second.']]],
            'dot wildcard' => ['$.rules[1].*', [['path' => '/rules/1/text', 'value' => 'Second.'], ['path' => '/rules/1/note', 'value' => ['text' => 'Nested.']]]],
            'recursive name' => ['$..text', [['path' => '/rules/0/text', 'value' => 'First.'], ['path' => '/rules/1/text', 'value' => 'Second.'], ['path' => '/rules/1/note/text', 'value' => 'Nested.']]],
            'recursive after child' => ['$.rules[1]..text', [['path' => '/rules/1/text', 'value' => 'Second.'], ['path' => '/rules/1/note/text', 'value' => 'Nested.']]],
            'name with digits and dashes' => ['$.rules_2-x', []],
            'missing name' => ['$.missing', []],
            'missing index' => ['$.rules[5]', []],
            'below a scalar' => ['$.other.text', []],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testSelectReadsDecodedObjects(): void
    {
        $document = json_decode('{"rules":[{"text":"First."}]}', false, 512, JSON_THROW_ON_ERROR);

        self::assertSame([['path' => '/rules/0/text', 'value' => 'First.']], (new JsonPath())->select($document, '$.rules[0].text'));
    }

    public function testSelectRequiresTheRoot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JSONPath must start with $.');
        (new JsonPath())->select([], 'rules[0]');
    }

    #[DataProvider('providerSelectUnsupported')]
    public function testSelectRejectsUnsupportedSyntax(string $path): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Supported JSONPath: child names, quoted keys, array indices, wildcards and recursive names.');
        (new JsonPath())->select(['rules' => [['text' => 'First.']]], $path);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSelectUnsupported(): array
    {
        return [
            'filter' => ['$.rules[?(@.text)]'],
            'negative index' => ['$.rules[-1]'],
            'slice' => ['$.rules[0:1]'],
            'name starting with a digit' => ['$.1rules'],
            'empty quoted key' => ["$['']"],
            'unterminated bracket' => ['$.rules[0'],
            'trailing dot' => ['$.rules.'],
            'triple dot' => ['$...text'],
            'second root' => ['$$'],
            'unsupported step after a match' => ['$.rules text'],
        ];
    }

    /**
     * @param list<array{path: string, value: mixed}> $expected
     */
    #[DataProvider('providerChildren')]
    public function testChildren(mixed $value, string $key, bool $recursive, array $expected): void
    {
        self::assertSame($expected, (new JsonPath())->children($value, '/root', $key, $recursive));
    }

    /**
     * @return array<string, array{mixed, string, bool, list<array{path: string, value: mixed}>}>
     */
    public static function providerChildren(): array
    {
        return [
            'scalar' => ['text', '*', true, []],
            'null' => [null, 'a', false, []],
            'named child' => [['a' => 1, 'b' => 2], 'b', false, [['path' => '/root/b', 'value' => 2]]],
            'every child' => [['a' => 1, 'b' => 2], '*', false, [['path' => '/root/a', 'value' => 1], ['path' => '/root/b', 'value' => 2]]],
            'index' => [['x', 'y'], '1', false, [['path' => '/root/1', 'value' => 'y']]],
            'not recursive' => [['a' => ['a' => 1]], 'a', false, [['path' => '/root/a', 'value' => ['a' => 1]]]],
            'recursive' => [['a' => ['a' => 1]], 'a', true, [['path' => '/root/a', 'value' => ['a' => 1]], ['path' => '/root/a/a', 'value' => 1]]],
            'recursive below a non-match' => [['b' => ['a' => 1]], 'a', true, [['path' => '/root/b/a', 'value' => 1]]],
            'escaped name' => [['~/' => 1], '~/', false, [['path' => '/root/~0~1', 'value' => 1]]],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testChildrenOfAnObject(): void
    {
        $value = json_decode('{"a":{"b":1}}', false, 512, JSON_THROW_ON_ERROR);

        self::assertSame([['path' => '/root/a/b', 'value' => 1]], (new JsonPath())->children($value, '/root', 'b', true));
    }
}
