<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Discovery;

use Deriver\Source\Discovery\DirectoryScan;
use Deriver\Source\Discovery\PathPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceDirectory;

#[CoversClass(DirectoryScan::class)]
#[UsesClass(PathPattern::class)]
#[Small]
final class DirectoryScanTest extends TestCase
{
    public function testFilesPruneExcludedDirectoriesAndSymbolicLinks(): void
    {
        $source = new SourceDirectory();
        $keep = $source->write('keep.php', '');
        $source->write('skip/sub/file.php', '');
        self::assertTrue(symlink($keep, $source->path . '/alias.php'));
        self::assertTrue(symlink($source->path, $source->path . '/loop'));
        $files = (new DirectoryScan())->files($source->path, [], [new PathPattern('skip')]);
        self::assertSame([$keep], iterator_to_array($files, false));
    }
}
