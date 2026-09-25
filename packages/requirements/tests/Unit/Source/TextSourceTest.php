<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\LocalFile;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use RuntimeException;
use Tests\Fake\ProjectDirectory;
use Tests\Fake\SourceDocuments;

#[CoversClass(TextSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(Source::class)]
#[UsesClass(Unit::class)]
#[Small]
final class TextSourceTest extends TestCase
{
    public function testSelectStableDistinctUnits(): void
    {
        $project = new ProjectDirectory();
        $project->put('source.md', SourceDocuments::MARKDOWN);
        $source = new Source('test', 'source.md', 'text', 'lines:1-5');
        $extension = new TextSource();
        $units = $extension->select($source, 'lines:1-5', $project->directory, false);

        self::assertCount(3, $units);
        self::assertCount(3, array_unique(array_map(static fn (Unit $unit): string => $unit->location, $units)));
        self::assertSame($units[0]->location, $extension->select($source, 'lines:1-5', $project->directory, false)[0]->location);
    }

    /**
     * @param list<Unit> $expected
     */
    #[DataProvider('providerSelectUnits')]
    public function testSelectLocatesNonblankLines(string $document, string $selector, array $expected): void
    {
        $project = new ProjectDirectory();
        $project->put('source.txt', $document);

        self::assertEquals($expected, (new TextSource())->select(new Source('test', 'source.txt', 'text', $selector), $selector, $project->directory, false));
    }

    /**
     * @return array<string, array{string, string, list<Unit>}>
     */
    public static function providerSelectUnits(): array
    {
        return [
            'range' => [SourceDocuments::MARKDOWN, 'lines:1-5', [
                new Unit('line:1', '# Rules'),
                new Unit('line:3', 'First **rule**.'),
                new Unit('line:5', 'Second rule.'),
            ]],
            'single line' => [SourceDocuments::MARKDOWN, 'lines:3', [new Unit('line:3', 'First **rule**.')]],
            'one-line range' => [SourceDocuments::MARKDOWN, 'lines:3-3', [new Unit('line:3', 'First **rule**.')]],
            'blank line' => [SourceDocuments::MARKDOWN, 'lines:2', []],
            'range ending at the last line' => [SourceDocuments::MARKDOWN, 'lines:5-6', [new Unit('line:5', 'Second rule.')]],
            'normalized' => ["  First\t rule. \n", 'lines:1', [new Unit('line:1', 'First rule.')]],
            'line endings' => ["First.\r\nSecond.\rThird.\nFourth.", 'lines:1-4', [
                new Unit('line:1', 'First.'),
                new Unit('line:2', 'Second.'),
                new Unit('line:3', 'Third.'),
                new Unit('line:4', 'Fourth.'),
            ]],
            'double-digit lines' => [str_repeat("Rule.\n", 11), 'lines:10-11', [new Unit('line:10', 'Rule.'), new Unit('line:11', 'Rule.')]],
        ];
    }

    #[DataProvider('providerSelectMalformed')]
    public function testSelectRejectsMalformedSelectors(string $selector): void
    {
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Text selectors use lines:START-END or lines:NUMBER.');
        (new TextSource())->select(new Source('test', 'missing.txt', 'text', $selector), $selector, $project->directory, false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSelectMalformed(): array
    {
        return [
            'zero' => ['lines:0'],
            'leading zero' => ['lines:01'],
            'open range' => ['lines:1-'],
            'zero end' => ['lines:1-0'],
            'singular' => ['line:1'],
            'prefix' => ['all lines:1'],
            'suffix' => ['lines:1-2x'],
            'trailing newline' => ["lines:1\n"],
            'css' => ['main p'],
        ];
    }

    #[DataProvider('providerSelectOutside')]
    public function testSelectRejectsRangesOutsideTheSource(string $selector): void
    {
        $project = new ProjectDirectory();
        $project->put('source.md', SourceDocuments::MARKDOWN);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Text line range is outside the source.');
        (new TextSource())->select(new Source('test', 'source.md', 'text', $selector), $selector, $project->directory, false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerSelectOutside(): array
    {
        return [
            'reversed' => ['lines:3-2'],
            'past the end' => ['lines:1-7'],
            'single line past the end' => ['lines:7'],
        ];
    }

    public function testSelectReadsThroughTheGivenLoader(): void
    {
        $project = new ProjectDirectory();
        $project->put('source.txt', 'Original.');
        $loader = new ResourceLoader();
        $loader->fetch($project->path('source.txt'));
        $project->put('source.txt', 'Changed.');

        self::assertEquals([new Unit('line:1', 'Original.')], (new TextSource($loader))->select(new Source('test', 'source.txt', 'text', 'lines:1'), 'lines:1', $project->directory, false));
    }
}
