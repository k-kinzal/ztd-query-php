<?php

declare(strict_types=1);

namespace Tests\Unit;

use Deriver\Exception\InvalidInputException;
use Deriver\Finder;
use Deriver\Source\Discovery\PathPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\SourceDirectory;

#[CoversClass(Finder::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(\Deriver\Source\Discovery\DirectoryScan::class)]
#[UsesClass(InvalidInputException::class)]
#[Small]
final class FinderTest extends TestCase
{
    public function testFilesApplyIncludesAndExcludesWithoutReadingPhp(): void
    {
        $source = new SourceDirectory();
        $one = $source->write('src/One.php', '<?php throw new RuntimeException("never execute");');
        $two = $source->write('src/nested/Two.php', 'not valid PHP');
        $source->write('src/nested/TwoTest.php', '<?php');
        $source->write('src/vendor/Ignore.php', '<?php');
        $source->write('README.md', 'text');
        $finder = (new Finder())->in($source->path)->include('src/**/*.php')->exclude('**/*Test.php', '**/vendor/**');
        self::assertSame([$one, $two], $finder->files());
    }

    public function testIncludeWildcardsRespectDirectoriesAndMatchZeroOrManyLevels(): void
    {
        $source = new SourceDirectory();
        $top = $source->write('A1.php', '');
        $nested = $source->write('nested/A2.php', '');
        $deep = $source->write('nested/deep/A3.php', '');
        $source->write('A12.php', '');
        self::assertSame([$top], (new Finder())->in($source->path)->include('A?.php')->files());
        self::assertSame([$top, $nested, $deep], (new Finder())->in($source->path)->include('**/A?.php')->files());
        self::assertSame([$nested], (new Finder())->in($source->path)->include('*/A?.php')->files());
    }

    public function testExcludeWinsAfterCombiningIncludesRegardlessOfCallOrder(): void
    {
        $source = new SourceDirectory();
        $keep = $source->write('src/Keep.php', '');
        $source->write('src/Drop.php', '');
        $extra = $source->write('extra.inc', '');
        $finder = (new Finder())->in($source->path)->exclude('**/Drop.php')->include('src/*.php')->include('extra.inc', 'src/Drop.php');
        self::assertSame([$extra, $keep], $finder->files());
    }

    public function testInCombinesOverlappingRootsIntoSortedUniqueAbsolutePaths(): void
    {
        $source = new SourceDirectory();
        $z = $source->write('z.php', '');
        $a = $source->write('nested/a.php', '');
        self::assertSame([$a, $z], (new Finder())->in($source->path . '/.', $source->path . '/nested')->in($source->path)->include('**/*.php')->files());
    }

    public function testEnumerationDoesNotRequireReadAccessToFileContents(): void
    {
        $source = new SourceDirectory();
        $path = $source->write('unreadable.php', '<?php');
        self::assertTrue(chmod($path, 0000));
        try {
            self::assertSame([$path], (new Finder())->in($source->path)->files());
        } finally {
            chmod($path, 0600);
        }
    }

    public function testDefaultsUseCurrentDirectoryAndDoNotImplicitlyExcludeFiles(): void
    {
        $source = new SourceDirectory();
        $hidden = $source->write('.hidden', '');
        $vendor = $source->write('vendor/file.txt', '');
        $previous = getcwd();
        self::assertIsString($previous);
        self::assertTrue(chdir($source->path));
        try {
            self::assertSame([$hidden, $vendor], (new Finder())->files());
            self::assertSame([], (new Finder())->include('**/*.php')->files());
        } finally {
            chdir($previous);
        }
    }

    public function testRepeatedEnumerationSeesNewFiles(): void
    {
        $source = new SourceDirectory();
        $finder = (new Finder())->in($source->path)->include('**/*.php');
        self::assertSame([], $finder->files());
        $path = $source->write('new.php', '');
        self::assertSame([$path], $finder->files());
    }

    #[DataProvider('providerInvalidRoots')]
    public function testRejectsInvalidRoots(string $root): void
    {
        $this->expectException(InvalidInputException::class);
        (new Finder())->in($root);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerInvalidRoots(): iterable
    {
        yield 'missing' => [__DIR__ . '/absent-root'];
        yield 'file' => [__FILE__];
        yield 'wrapper' => ['file://' . __DIR__];
    }
}
