<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Requirements\Source\LocalFile;
use RuntimeException;
use Tests\Fake\ProjectDirectory;

#[CoversClass(LocalFile::class)]
#[Medium]
final class LocalFileTest extends TestCase
{
    public function testReadReturnsTheContents(): void
    {
        $project = new ProjectDirectory();

        self::assertSame("Names shall start with a letter.\n", (new LocalFile())->read($project->put('notes.txt', "Names shall start with a letter.\n")));
    }

    public function testReadReturnsAnEmptyFile(): void
    {
        $project = new ProjectDirectory();

        self::assertSame('', (new LocalFile())->read($project->put('empty.txt', '')));
    }

    public function testReadReturnsNothingForADirectory(): void
    {
        $project = new ProjectDirectory();

        self::assertSame('', (new LocalFile())->read($project->directory));
    }

    public function testReadAcceptsTheSizeLimit(): void
    {
        $project = new ProjectDirectory();

        self::assertSame(16777216, strlen((new LocalFile())->read($project->put('large.txt', str_repeat('x', 16777216)))));
    }

    public function testReadRejectsContentsOverTheSizeLimit(): void
    {
        $project = new ProjectDirectory();
        $path = $project->put('large.txt', str_repeat('x', 16777217));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unreadable resource or 16 MiB size limit exceeded.');
        (new LocalFile())->read($path);
    }

    public function testReadRejectsReadableNonFiles(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unreadable resource or 16 MiB size limit exceeded.');
        (new LocalFile())->read('/dev/null');
    }

    public function testReadRejectsMissingFiles(): void
    {
        $project = new ProjectDirectory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unreadable resource or 16 MiB size limit exceeded.');
        (new LocalFile())->read($project->path('missing.txt'));
    }
}
