<?php

declare (strict_types=1);

namespace Tests\Unit\Finder;

use Deriver\Finder\DirectoryScan;
use Deriver\Finder\PathPattern;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectoryScan::class)]
#[UsesClass(PathPattern::class)]
#[Small]
final class DirectoryScanTest extends TestCase
{
    /**
     * @throws \org\bovigo\vfs\vfsStreamException
     */
    public function testFilesPruneExcludedDirectoriesAndSymbolicLinks(): void
    {
        $root = vfsStream::setup('project', null, ['keep.php' => '', 'skip' => ['sub' => ['file.php' => '']]]);
        /** vfsStream has no link targets; link mode exercises lstat-based pruning. */
        (new class ('alias.php') extends vfsStreamFile {
            #[Override]
            public function getType(): int
            {
                return 0120000;
            }
        })->at($root);
        (new class ('loop') extends vfsStreamDirectory {
            #[Override]
            public function getType(): int
            {
                return 0120000;
            }
        })->at($root);
        self::assertTrue(is_link($root->url() . '/alias.php'));
        self::assertTrue(is_link($root->url() . '/loop'));
        $files = (new DirectoryScan())->files($root->url(), [], [new PathPattern('skip')]);
        self::assertSame([$root->url() . '/keep.php'], iterator_to_array($files, false));
    }
}
