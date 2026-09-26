<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Source;
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\LocalFile;
use Requirements\Source\Registry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextFragment;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use stdClass;
use Tests\Fake\MemorySource;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Registry::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextFragment::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(Unit::class)]
#[Small]
final class RegistryTest extends TestCase
{
    /**
     * @param class-string $class
     */
    #[DataProvider('providerGetBuiltIn')]
    public function testGetBuiltInExtension(string $format, string $class): void
    {
        self::assertInstanceOf($class, (new Registry())->get($format));
    }

    /**
     * @return array<string, array{string, class-string}>
     */
    public static function providerGetBuiltIn(): array
    {
        return [
            'html' => ['html', DomSource::class],
            'xml' => ['xml', DomSource::class],
            'ietf' => ['ietf', DomSource::class],
            'markdown' => ['markdown', DomSource::class],
            'json' => ['json', JsonSource::class],
            'text' => ['text', TextSource::class],
        ];
    }

    public function testGetSharesOneDocumentExtension(): void
    {
        $registry = new Registry();

        self::assertSame($registry->get('html'), $registry->get('markdown'));
    }

    public function testGetSharesOneLoaderAcrossFormats(): void
    {
        $project = new ProjectDirectory();
        $registry = new Registry();
        $registry->get('html')->select(new Source('manual', 'source.html', 'html', 'main p'), 'main p', $project->directory, false);
        $project->put('source.html', 'Changed.');

        self::assertEquals([new Unit('line:1', '<!DOCTYPE html>')], $registry->get('text')->select(new Source('manual', 'source.html', 'text', 'lines:1'), 'lines:1', $project->directory, false));
    }

    public function testGetConfiguredExtension(): void
    {
        self::assertInstanceOf(MemorySource::class, (new Registry(['memory' => MemorySource::class]))->get('memory'));
    }

    public function testGetConfiguredExtensionReplacesABuiltIn(): void
    {
        $registry = new Registry(['html' => MemorySource::class]);

        self::assertInstanceOf(MemorySource::class, $registry->get('html'));
        self::assertInstanceOf(DomSource::class, $registry->get('xml'));
    }

    public function testGetRejectsUnknownFormats(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown source extension: memory');
        (new Registry())->get('memory');
    }

    #[DataProvider('providerConfiguredNonExtension')]
    public function testGetRejectsConfiguredNonExtensions(string $class): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage("$class must implement SourceExtension.");
        (new Registry(['memory' => MemorySource::class, 'other' => $class]))->get('memory');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerConfiguredNonExtension(): array
    {
        return [
            'plain class' => [stdClass::class],
            'missing class' => ['Tests\Fake\MissingSource'],
        ];
    }
}
