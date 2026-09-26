<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Source\SnapshotCache;
use Tests\Fake\ProjectDirectory;

#[CoversClass(SnapshotCache::class)]
#[Small]
final class SnapshotCacheTest extends TestCase
{
    public function testWriteCreatesTheSnapshotAndItsDirectories(): void
    {
        $project = new ProjectDirectory();
        (new SnapshotCache())->write($project->path('.requirements-cache/sources/manual.html'), 'Verified text.');

        self::assertSame('Verified text.', $project->read('.requirements-cache/sources/manual.html'));
    }

    public function testWriteCreatesDirectoriesReadableByOthers(): void
    {
        $project = new ProjectDirectory();
        (new SnapshotCache())->write($project->path('cache/sources/manual.html'), 'Verified text.');

        self::assertSame(0755 & ~umask(), fileperms($project->path('cache/sources')) & 0777);
    }

    public function testWriteReplacesAnExistingSnapshot(): void
    {
        $project = new ProjectDirectory();
        (new SnapshotCache())->write($project->put('cache/manual.html', 'Old text.'), 'New text.');

        self::assertSame('New text.', $project->read('cache/manual.html'));
    }

    public function testWriteLeavesNoTemporaryFile(): void
    {
        $project = new ProjectDirectory();
        (new SnapshotCache())->write($project->path('cache/manual.html'), 'Verified text.');

        self::assertSame(['manual.html'], array_values(array_diff((array) scandir($project->path('cache')), ['.', '..'])));
    }
}
