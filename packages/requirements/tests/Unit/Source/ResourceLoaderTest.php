<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Model\Source;
use Requirements\Source\Download;
use Requirements\Source\LocalFile;
use Requirements\Source\ResourceLoader;
use Requirements\Source\ResourceLocation;
use Requirements\Source\SnapshotCache;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Fake\ProjectDirectory;
use Tests\Fake\SourceFailure;

#[CoversClass(ResourceLoader::class)]
#[UsesClass(Download::class)]
#[UsesClass(LocalFile::class)]
#[UsesClass(ResourceLocation::class)]
#[UsesClass(SnapshotCache::class)]
#[UsesClass(Source::class)]
#[Small]
final class ResourceLoaderTest extends TestCase
{
    public function testReadResolvesPathsAgainstTheDirectory(): void
    {
        $project = new ProjectDirectory();
        $project->put('docs/notes.txt', 'Names start with a letter.');

        self::assertSame('Names start with a letter.', (new ResourceLoader())->read(new Source('notes', 'docs/notes.txt', 'text', 'lines:1'), $project->directory, false));
    }

    public function testReadAcceptsAMatchingDigest(): void
    {
        $project = new ProjectDirectory();
        $project->put('notes.txt', 'Names start with a letter.');
        $source = new Source('notes', 'notes.txt', 'text', 'lines:1', sha256: hash('sha256', 'Names start with a letter.'));

        self::assertSame('Names start with a letter.', (new ResourceLoader())->read($source, $project->directory, false));
    }

    public function testReadRejectsADifferentDigest(): void
    {
        $project = new ProjectDirectory();
        $project->put('notes.txt', 'Names start with a digit.');
        $source = new Source('notes', 'notes.txt', 'text', 'lines:1', sha256: hash('sha256', 'Names start with a letter.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('notes: source SHA-256 mismatch.');
        (new ResourceLoader())->read($source, $project->directory, false);
    }

    public function testReadLiveStillVerifiesASourceWithoutSnapshot(): void
    {
        $project = new ProjectDirectory();
        $project->put('notes.txt', 'Names start with a digit.');
        $source = new Source('notes', 'notes.txt', 'text', 'lines:1', sha256: hash('sha256', 'Names start with a letter.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('notes: source SHA-256 mismatch.');
        (new ResourceLoader())->read($source, $project->directory, true);
    }

    public function testReadLiveModeBypassesPinnedSnapshotAndDetectsCurrentText(): void
    {
        $project = new ProjectDirectory();
        $project->put('current.txt', 'New text.');
        $project->put('snapshot.txt', 'Old text.');
        $source = new Source('test', 'current.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Old text.'));
        $loader = new ResourceLoader();

        self::assertSame('Old text.', $loader->read($source, $project->directory, false));
        self::assertSame('New text.', $loader->read($source, $project->directory, true));
    }

    public function testReadPrefersAnExistingSnapshotOverTheUri(): void
    {
        $project = new ProjectDirectory();
        $project->put('snapshot.txt', 'Pinned text.');
        $source = new Source('test', 'missing.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Pinned text.'));

        self::assertSame('Pinned text.', (new ResourceLoader())->read($source, $project->directory, false));
    }

    public function testReadRejectsAChangedSnapshot(): void
    {
        $project = new ProjectDirectory();
        $project->put('current.txt', 'Pinned text.');
        $project->put('snapshot.txt', 'Changed text.');
        $source = new Source('test', 'current.txt', 'text', 'lines:1', 'snapshot.txt', hash('sha256', 'Pinned text.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test: source SHA-256 mismatch.');
        (new ResourceLoader())->read($source, $project->directory, false);
    }

    public function testReadMissingSnapshotIsFetchedAndVerifiedBeforeCaching(): void
    {
        $project = new ProjectDirectory();
        $content = 'Original source text.';
        $project->put('upstream.txt', $content);
        $source = new Source('test', 'upstream.txt', 'text', 'lines:1', '.requirements-cache/manual.txt', hash('sha256', $content));

        self::assertSame($content, (new ResourceLoader())->read($source, $project->directory, false));
        unlink($project->path('upstream.txt'));
        self::assertSame($content, (new ResourceLoader())->read($source, $project->directory, false));
        $project->put('.requirements-cache/manual.txt', 'Changed text.');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA-256');
        (new ResourceLoader())->read($source, $project->directory, false);
    }

    public function testReadCachesAMissingSnapshotVerbatim(): void
    {
        $project = new ProjectDirectory();
        $project->put('upstream.txt', "Original source text.\n");
        $source = new Source('test', 'upstream.txt', 'text', 'lines:1', '.requirements-cache/manual.txt', hash('sha256', "Original source text.\n"));
        (new ResourceLoader())->read($source, $project->directory, false);

        self::assertSame("Original source text.\n", $project->read('.requirements-cache/manual.txt'));
    }

    public function testReadDownloadsAMissingSnapshotOnce(): void
    {
        $project = new ProjectDirectory();
        $client = new MockHttpClient([new MockResponse('Remote text.')]);
        $source = new Source('test', 'https://example.org/manual', 'text', 'lines:1', 'cache/manual.txt', hash('sha256', 'Remote text.'));

        self::assertSame('Remote text.', (new ResourceLoader($client))->read($source, $project->directory, false));
        self::assertSame('Remote text.', (new ResourceLoader($client))->read($source, $project->directory, false));
        self::assertSame(1, $client->getRequestsCount());
        self::assertSame('Remote text.', $project->read('cache/manual.txt'));
    }

    public function testReadRejectsAnUnverifiedDownload(): void
    {
        $project = new ProjectDirectory();
        $project->put('upstream.txt', 'Changed text.');
        $source = new Source('test', 'upstream.txt', 'text', 'lines:1', '.requirements-cache/manual.txt', hash('sha256', 'Original text.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHA-256');
        (new ResourceLoader())->read($source, $project->directory, false);
    }

    public function testReadUnverifiedDownloadIsNeverCached(): void
    {
        $project = new ProjectDirectory();
        $project->put('upstream.txt', 'Changed text.');
        $source = new Source('test', 'upstream.txt', 'text', 'lines:1', '.requirements-cache/manual.txt', hash('sha256', 'Original text.'));

        self::assertStringContainsString('SHA-256', SourceFailure::message(static fn (): string => (new ResourceLoader())->read($source, $project->directory, false)));
        self::assertFileDoesNotExist($project->path('.requirements-cache/manual.txt'));
    }

    public function testReadRemoteSnapshotIsNeverCachedLocally(): void
    {
        $project = new ProjectDirectory();
        $snapshot = new MockResponse('Pinned text.');
        $source = new Source('test', 'https://example.org/manual', 'text', 'lines:1', 'https://example.org/snapshot', hash('sha256', 'Pinned text.'));

        self::assertSame('Pinned text.', (new ResourceLoader(new MockHttpClient([$snapshot])))->read($source, $project->directory, false));
        self::assertSame('https://example.org/snapshot', $snapshot->getRequestUrl());
    }

    public function testReadHttpContentIsBoundedAndFailuresAreNotRepeated(): void
    {
        $client = new MockHttpClient([new MockResponse(str_repeat('x', 16777217))]);
        $loader = new ResourceLoader($client);
        $source = new Source('test', 'https://example.org/manual', 'text', 'lines:1');

        self::assertStringContainsString('16 MiB', SourceFailure::message(static fn (): string => $loader->read($source, '.', false)));
        self::assertStringContainsString('16 MiB', SourceFailure::message(static fn (): string => $loader->read($source, '.', false)));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testReadHttpErrorCannotBecomeValidEvidence(): void
    {
        $loader = new ResourceLoader(new MockHttpClient(new MockResponse('Names shall start with a letter.', ['http_code' => 404])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 404');
        $loader->read(new Source('test', 'https://example.org/missing', 'text', 'lines:1'), '.', false);
    }

    public function testReadHttpContentIsCachedWithinTheCommand(): void
    {
        $client = new MockHttpClient(new MockResponse('Source text.'));
        $loader = new ResourceLoader($client);
        $source = new Source('test', 'https://example.org/manual', 'text', 'lines:1');

        self::assertSame('Source text.', $loader->read($source, '.', false));
        self::assertSame('Source text.', $loader->read($source, '.', false));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testReadRejectsOtherSchemes(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Built-in sources accept HTTP(S) URLs or local paths only.');
        (new ResourceLoader())->read(new Source('test', 'file:///etc/hostname', 'text', 'lines:1'), '.', false);
    }

    public function testFetchRemembersTheDocument(): void
    {
        $project = new ProjectDirectory();
        $path = $project->put('notes.txt', 'First text.');
        $loader = new ResourceLoader();

        self::assertSame('First text.', $loader->fetch($path));
        $project->put('notes.txt', 'Second text.');
        self::assertSame('First text.', $loader->fetch($path));
        self::assertSame('Second text.', (new ResourceLoader())->fetch($path));
    }

    public function testFetchNamesTheUnreadableResource(): void
    {
        $project = new ProjectDirectory();
        $path = $project->path('missing.txt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot read source $path: Unreadable resource or 16 MiB size limit exceeded.");
        $this->expectExceptionCode(0);
        (new ResourceLoader())->fetch($path);
    }

    public function testFetchRemembersTheFailure(): void
    {
        $project = new ProjectDirectory();
        $path = $project->path('late.txt');
        $loader = new ResourceLoader();

        self::assertSame("Cannot read source $path: Unreadable resource or 16 MiB size limit exceeded.", SourceFailure::message(static fn (): string => $loader->fetch($path)));
        $project->put('late.txt', 'Too late.');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot read source $path: Unreadable resource or 16 MiB size limit exceeded.");
        $loader->fetch($path);
    }

    public function testFetchWrapsTransportFailures(): void
    {
        $loader = new ResourceLoader(new MockHttpClient(new MockResponse('', ['error' => 'Connection refused.'])));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read source https://example.org/manual: Connection refused.');
        $loader->fetch('https://example.org/manual');
    }

    public function testFetchWrapsClientFailures(): void
    {
        $loader = new ResourceLoader(new MockHttpClient(new MockResponse('Source text.')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read source https://: Malformed URL "https://".');
        $loader->fetch('https://');
    }
}
