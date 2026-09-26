<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\LocalFile;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\SourceExtension;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use Tests\Fake\ProjectDirectory;

#[CoversClass(SourceExtension::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(Source::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(Unit::class)]
#[Small]
final class SourceExtensionTest extends TestCase
{
    public function testSelectReturnsTheUnitsOfTheScopeOrOfAnEvidenceSelector(): void
    {
        $project = new ProjectDirectory();
        $project->put('notes.txt', "Names start with a letter.\nNames may contain digits.\n");
        $source = new Source('notes', 'notes.txt', 'text', 'lines:1-2');
        $extension = new TextSource();

        self::assertEquals([new Unit('line:1', 'Names start with a letter.'), new Unit('line:2', 'Names may contain digits.')], $extension->select($source, $source->selector, $project->directory, false));
        self::assertEquals([new Unit('line:2', 'Names may contain digits.')], $extension->select($source, 'lines:2', $project->directory, false));
    }
}
