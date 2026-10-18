<?php

declare (strict_types=1);

namespace Tests\Unit;

use Deriver\Deriver\Sources\Path;
use Deriver\Exception\InvalidInputException;
use Deriver\Finder;
use Deriver\Finder\DirectoryScan;
use Deriver\Finder\PathPattern;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Finder::class)]
#[UsesClass(PathPattern::class)]
#[UsesClass(DirectoryScan::class)]
#[UsesClass(InvalidInputException::class)]
#[Small]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FinderTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testFilesApplyIncludesAndExcludesWithoutReadingPhp(): void
    {
        $filesystem = vfsStream::setup('project', null, ['src' => ['nested' => [], 'vendor' => []]]);
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $name = 'src/One.php';
        $contents = '<?php throw new RuntimeException("never execute");';
        $path = $source->url() . '/' . $name;
        file_put_contents($path, $contents);
        $one = $path;
        $name1 = 'src/nested/Two.php';
        $contents1 = 'not valid PHP';
        $path1 = $source->url() . '/' . $name1;
        file_put_contents($path1, $contents1);
        $two = $path1;
        $name2 = 'src/nested/TwoTest.php';
        $contents2 = '<?php';
        $path2 = $source->url() . '/' . $name2;
        file_put_contents($path2, $contents2);
        $name3 = 'src/vendor/Ignore.php';
        $contents3 = '<?php';
        $path3 = $source->url() . '/' . $name3;
        file_put_contents($path3, $contents3);
        $name4 = 'README.md';
        $contents4 = 'text';
        $path4 = $source->url() . '/' . $name4;
        file_put_contents($path4, $contents4);
        $finder = (new Finder())->in('/project')->include('src/**/*.php')->exclude('**/*Test.php', '**/vendor/**');
        self::assertSame([$one, $two], $finder->files());
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testIncludeWildcardsRespectDirectoriesAndMatchZeroOrManyLevels(): void
    {
        $filesystem = vfsStream::setup('project', null, ['nested' => ['deep' => []]]);
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $name = 'A1.php';
        $contents = '';
        $path = $source->url() . '/' . $name;
        file_put_contents($path, $contents);
        $top = $path;
        $name1 = 'nested/A2.php';
        $contents1 = '';
        $path1 = $source->url() . '/' . $name1;
        file_put_contents($path1, $contents1);
        $nested = $path1;
        $name2 = 'nested/deep/A3.php';
        $contents2 = '';
        $path2 = $source->url() . '/' . $name2;
        file_put_contents($path2, $contents2);
        $deep = $path2;
        $name3 = 'A12.php';
        $contents3 = '';
        $path3 = $source->url() . '/' . $name3;
        file_put_contents($path3, $contents3);
        self::assertSame([$top], (new Finder())->in('/project')->include('A?.php')->files());
        self::assertSame([$top, $nested, $deep], (new Finder())->in('/project')->include('**/A?.php')->files());
        self::assertSame([$nested], (new Finder())->in('/project')->include('*/A?.php')->files());
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testExcludeWinsAfterCombiningIncludesRegardlessOfCallOrder(): void
    {
        $filesystem = vfsStream::setup('project', null, ['src' => []]);
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $name = 'src/Keep.php';
        $contents = '';
        $path = $source->url() . '/' . $name;
        file_put_contents($path, $contents);
        $keep = $path;
        $name1 = 'src/Drop.php';
        $contents1 = '';
        $path1 = $source->url() . '/' . $name1;
        file_put_contents($path1, $contents1);
        $name2 = 'extra.inc';
        $contents2 = '';
        $path2 = $source->url() . '/' . $name2;
        file_put_contents($path2, $contents2);
        $extra = $path2;
        $finder = (new Finder())->in('/project')->exclude('**/Drop.php')->include('src/*.php')->include('extra.inc', 'src/Drop.php');
        self::assertSame([$extra, $keep], $finder->files());
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testInCombinesOverlappingRootsIntoSortedUniqueAbsolutePaths(): void
    {
        $filesystem = vfsStream::setup('project', null, ['nested' => []]);
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $name = 'z.php';
        $contents = '';
        $path = $source->url() . '/' . $name;
        file_put_contents($path, $contents);
        $z = $path;
        $name1 = 'nested/a.php';
        $contents1 = '';
        $path1 = $source->url() . '/' . $name1;
        file_put_contents($path1, $contents1);
        $a = $path1;
        self::assertSame([$a, $z], (new Finder())->in('/project' . '/.', '/project' . '/nested')->in('/project')->include('**/*.php')->files());
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testEnumerationDoesNotRequireReadAccessToFileContents(): void
    {
        $filesystem = vfsStream::setup('project');
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $name = 'unreadable.php';
        $contents = '<?php';
        $path1 = $source->url() . '/' . $name;
        file_put_contents($path1, $contents);
        $path = $path1;
        self::assertTrue(chmod($path, 00));
        try {
            self::assertSame([$path], (new Finder())->in('/project')->files());
        } finally {
            chmod($path, 0600);
        }
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDefaultsUseCurrentDirectoryAndDoNotImplicitlyExcludeFiles(): void
    {
        $filesystem = vfsStream::setup('project', null, ['vendor' => []]);
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $name = '.hidden';
        $contents = '';
        $path = $source->url() . '/' . $name;
        file_put_contents($path, $contents);
        $hidden = $path;
        $name1 = 'vendor/file.txt';
        $contents1 = '';
        $path1 = $source->url() . '/' . $name1;
        file_put_contents($path1, $contents1);
        $vendor = $path1;
        self::assertSame([$hidden, $vendor], (new Finder())->files());
        self::assertSame([], (new Finder())->include('**/*.php')->files());
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testRepeatedEnumerationSeesNewFiles(): void
    {
        $filesystem = vfsStream::setup('project');
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
        $source = $filesystem;
        $finder = (new Finder())->in('/project')->include('**/*.php');
        self::assertSame([], $finder->files());
        $name = 'new.php';
        $contents = '';
        $path1 = $source->url() . '/' . $name;
        file_put_contents($path1, $contents);
        $path = $path1;
        self::assertSame([$path], $finder->files());
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    #[DataProvider('providerInvalidRoots')]
    public function testRejectsInvalidRoots(string $root): void
    {
        $filesystem = vfsStream::setup('project');
        $url = static fn (string $path): string => 'vfs:/' . Path::normalize($path === '.' ? '/project' : $path);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable($url($path)))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction($url)->build()->enable();
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
    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
