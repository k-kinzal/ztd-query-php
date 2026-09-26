<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Excerpt;
use Requirements\Model\Source;
use Requirements\Report\EvidenceMatcher;
use Requirements\Source\DomSource;
use Requirements\Source\JsonSource;
use Requirements\Source\LocalFile;
use Requirements\Source\Registry;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\TextFragment;
use Requirements\Source\TextSource;
use Requirements\Source\Unit;
use RuntimeException;
use Tests\Fake\MemorySource;
use Tests\Fake\ProjectDirectory;

#[CoversClass(EvidenceMatcher::class)]
#[UsesClass(Excerpt::class)]
#[UsesClass(Source::class)]
#[UsesClass(Registry::class)]
#[UsesClass(DomSource::class)]
#[UsesClass(JsonSource::class)]
#[UsesClass(TextSource::class)]
#[UsesClass(ResourceLoader::class)]
#[UsesClass(Unit::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(TextFragment::class)]
#[Small]
final class EvidenceMatcherTest extends TestCase
{
    #[DataProvider('providerMatchingQuotes')]
    public function testMatchReturnsTheKeyOfTheQuotedUnit(string $quote): void
    {
        $source = new Source('memory', 'memory:message', 'memory', '*');
        $key = (new Unit('message:1', 'A service message.'))->key($source);
        $matcher = new EvidenceMatcher(new Registry(['memory' => MemorySource::class]), '/', false);
        self::assertSame($key, $matcher->match($source, new Excerpt('message', $quote), ['memory' => ['other', $key]]));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerMatchingQuotes(): array
    {
        return [
            'exact' => ['A service message.'],
            'whitespace differs' => ["  A\n\tservice\u{00a0} message. "],
        ];
    }

    public function testMatchRejectsASelectorMatchingNoUnit(): void
    {
        $project = new ProjectDirectory();
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $matcher = new EvidenceMatcher(new Registry(), $project->directory, false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Evidence must select exactly one unit: #missing');
        $matcher->match($source, new Excerpt('#missing', 'Nothing.'), ['manual' => []]);
    }

    public function testMatchRejectsASelectorMatchingSeveralUnits(): void
    {
        $project = new ProjectDirectory();
        $source = new Source('manual', 'source.html', 'html', 'main p');
        $matcher = new EvidenceMatcher(new Registry(), $project->directory, false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Evidence must select exactly one unit: main p');
        $matcher->match($source, new Excerpt('main p', 'Names shall start with a letter.'), ['manual' => []]);
    }

    public function testMatchRejectsAUnitOutsideTheScope(): void
    {
        $source = new Source('memory', 'memory:message', 'memory', '*');
        $matcher = new EvidenceMatcher(new Registry(['memory' => MemorySource::class]), '/', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Evidence is outside the declared scope: message');
        $matcher->match($source, new Excerpt('message', 'A service message.'), ['memory' => [], 'other' => [(new Unit('message:1', 'A service message.'))->key($source)]]);
    }

    public function testMatchRejectsAPartialQuotation(): void
    {
        $source = new Source('memory', 'memory:message', 'memory', '*');
        $matcher = new EvidenceMatcher(new Registry(['memory' => MemorySource::class]), '/', false);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quotation differs from the complete source unit: message');
        $matcher->match($source, new Excerpt('message', 'A service'), ['memory' => [(new Unit('message:1', 'A service message.'))->key($source)]]);
    }

    public function testMatchRejectsAnUnknownFormat(): void
    {
        $source = new Source('manual', 'source.txt', 'unknown', '*');
        $matcher = new EvidenceMatcher(new Registry(), '/', false);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unknown source extension: unknown');
        $matcher->match($source, new Excerpt('line:1', 'Text.'), ['manual' => []]);
    }

    #[DataProvider('providerReadingModes')]
    public function testMatchReadsTheSnapshotUnlessLive(bool $live, string $quote): void
    {
        $project = new ProjectDirectory();
        $project->put('current.txt', 'New text.');
        $project->put('snapshot.txt', 'Old text.');
        $source = new Source('notes', 'current.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Old text.'));
        $key = (new Unit('line:1', $quote))->key($source);
        $matcher = new EvidenceMatcher(new Registry(), $project->directory, $live);
        self::assertSame($key, $matcher->match($source, new Excerpt('lines:1', $quote), ['notes' => [$key]]));
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function providerReadingModes(): array
    {
        return [
            'snapshot' => [false, 'Old text.'],
            'live' => [true, 'New text.'],
        ];
    }

    #[DataProvider('providerStaleQuotes')]
    public function testMatchRejectsTheTextOfTheOtherReadingMode(bool $live, string $quote): void
    {
        $project = new ProjectDirectory();
        $project->put('current.txt', 'New text.');
        $project->put('snapshot.txt', 'Old text.');
        $source = new Source('notes', 'current.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Old text.'));
        $matcher = new EvidenceMatcher(new Registry(), $project->directory, $live);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Quotation differs from the complete source unit: lines:1');
        $matcher->match($source, new Excerpt('lines:1', $quote), ['notes' => [(new Unit('line:1', $quote))->key($source)]]);
    }

    /**
     * @return array<string, array{bool, string}>
     */
    public static function providerStaleQuotes(): array
    {
        return [
            'snapshot' => [false, 'New text.'],
            'live' => [true, 'Old text.'],
        ];
    }
}
