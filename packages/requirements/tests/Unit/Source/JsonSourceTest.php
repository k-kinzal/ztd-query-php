<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\JsonPath;
use Requirements\Source\JsonSource;
use Requirements\Source\LocalFile;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\Unit;
use RuntimeException;
use Tests\Fake\ProjectDirectory;
use Tests\Fake\SourceDocuments;

#[CoversClass(JsonSource::class)]
#[UsesClass(JsonPath::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[Small]
final class JsonSourceTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[DataProvider('providerSelectStableDistinctUnits')]
    public function testSelectStableDistinctUnits(string $selector, int $count): void
    {
        $project = new ProjectDirectory();
        $project->put('source.json', SourceDocuments::JSON);
        $source = new Source('test', 'source.json', 'json', $selector);
        $extension = new JsonSource();
        $units = $extension->select($source, $selector, $project->directory, false);

        self::assertCount($count, $units);
        self::assertCount($count, array_unique(array_map(static fn (Unit $unit): string => $unit->location, $units)));
        self::assertSame($units[0]->location, $extension->select($source, $selector, $project->directory, false)[0]->location);
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function providerSelectStableDistinctUnits(): array
    {
        return [
            'wildcard' => ['$.rules[*].text', 3],
            'recursive' => ['$..text', 3],
            'quoted keys' => ["$['rules'][1]['text']", 1],
        ];
    }

    /**
     * @param list<Unit> $expected
     * @throws JsonException
     */
    #[DataProvider('providerSelectUnits')]
    public function testSelectLocatesUnitsByJsonPointer(string $document, string $selector, array $expected): void
    {
        $project = new ProjectDirectory();
        $project->put('source.json', $document);

        self::assertEquals($expected, (new JsonSource())->select(new Source('test', 'source.json', 'json', $selector), $selector, $project->directory, false));
    }

    /**
     * @return array<string, array{string, string, list<Unit>}>
     */
    public static function providerSelectUnits(): array
    {
        return [
            'strings' => [SourceDocuments::JSON, '$.rules[*].text', [
                new Unit('json:/rules/0/text', 'First rule.'),
                new Unit('json:/rules/1/text', 'First rule.'),
                new Unit('json:/rules/2/text', 'Third rule.'),
            ]],
            'each path once' => [SourceDocuments::JSON, '$..*..text', [
                new Unit('json:/rules/0/text', 'First rule.'),
                new Unit('json:/rules/1/text', 'First rule.'),
                new Unit('json:/rules/2/text', 'Third rule.'),
            ]],
            'normalized string' => ['{"text":"  First\n\trule. "}', '$.text', [new Unit('json:/text', 'First rule.')]],
            'number' => ['{"n":1.5}', '$.n', [new Unit('json:/n', '1.5')]],
            'boolean' => ['{"b":true}', '$.b', [new Unit('json:/b', 'true')]],
            'null' => ['{"z":null}', '$.z', [new Unit('json:/z', 'null')]],
            'object' => ['{"o":{"url":"a/b","name":"日本"}}', '$.o', [new Unit('json:/o', '{"url":"a/b","name":"日本"}')]],
            'empty object' => ['{"o":{}}', '$.o', [new Unit('json:/o', '{}')]],
            'array' => ['{"a":[1,"x"]}', '$.a', [new Unit('json:/a', '[1,"x"]')]],
            'nothing selected' => [SourceDocuments::JSON, '$.missing', []],
        ];
    }

    /**
     * @throws JsonException
     */
    public function testSelectRejectsUnsupportedJsonPathInsteadOfSilentlySelecting(): void
    {
        $project = new ProjectDirectory();
        $project->put('source.json', SourceDocuments::JSON);
        $source = new Source('test', 'source.json', 'json', '$.rules[?(@.text)]');

        $this->expectException(RuntimeException::class);
        (new JsonSource())->select($source, $source->selector, $project->directory, false);
    }

    /**
     * @throws JsonException
     */
    public function testSelectRejectsInvalidJson(): void
    {
        $project = new ProjectDirectory();
        $project->put('source.json', '{"rules":');

        $this->expectException(JsonException::class);
        (new JsonSource())->select(new Source('test', 'source.json', 'json', '$'), '$', $project->directory, false);
    }

    /**
     * @throws JsonException
     */
    public function testSelectAcceptsTheNestingLimit(): void
    {
        $project = new ProjectDirectory();
        $project->put('deep.json', str_repeat('[', 511) . str_repeat(']', 511));

        self::assertCount(1, (new JsonSource())->select(new Source('test', 'deep.json', 'json', '$[0]'), '$[0]', $project->directory, false));
    }

    /**
     * @throws JsonException
     */
    public function testSelectRejectsDocumentsNestedBeyondTheLimit(): void
    {
        $project = new ProjectDirectory();
        $project->put('deep.json', str_repeat('[', 512) . str_repeat(']', 512));

        $this->expectException(JsonException::class);
        (new JsonSource())->select(new Source('test', 'deep.json', 'json', '$[0]'), '$[0]', $project->directory, false);
    }

    /**
     * @throws JsonException
     */
    public function testSelectReadsThroughTheGivenLoader(): void
    {
        $project = new ProjectDirectory();
        $project->put('source.json', SourceDocuments::JSON);
        $loader = new ResourceLoader();
        $loader->fetch($project->path('source.json'));
        $project->put('source.json', '{"other":"Changed."}');

        self::assertEquals([new Unit('json:/other', 'Outside scope.')], (new JsonSource($loader))->select(new Source('test', 'source.json', 'json', '$.other'), '$.other', $project->directory, false));
    }
}
