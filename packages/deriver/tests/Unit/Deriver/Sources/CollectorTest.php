<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Sources;

use Deriver\Deriver\Sources\Collector;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\CollectorException;
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

#[CoversClass(Collector::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(Sources::class)]
#[Small]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CollectorTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCollectReadsOnlySelectedFilesInCallerOrderWithoutExecutingOrParsing(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $directory = $filesystem;
        $throwing = '<?php throw new RuntimeException("must not execute");';
        $malformed = "<?php function invalid( \xff";
        $name = 'z.php';
        $url = $directory->url() . '/' . $name;
        file_put_contents($url, $throwing);
        $last = '/' . $directory->path() . '/' . $name;
        $name1 = 'a.php';
        $url1 = $directory->url() . '/' . $name1;
        file_put_contents($url1, $malformed);
        $first = '/' . $directory->path() . '/' . $name1;
        $name2 = 'unselected.php';
        $contents = '<?php exit(1);';
        $url2 = $directory->url() . '/' . $name2;
        file_put_contents($url2, $contents);
        $sources = (new Collector())->collect([$last, $first]);
        self::assertCount(2, $sources);
        self::assertSame($last, $sources[0]->path);
        self::assertSame($throwing, $sources[0]->contents);
        self::assertFalse($sources[0]->declarationsOnly);
        self::assertSame($first, $sources[1]->path);
        self::assertSame($malformed, $sources[1]->contents);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCollectAcceptsAnEmptySelection(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        self::assertCount(0, (new Collector())->collect([]));
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCollectCapturesFreshContentsAndPreservesEarlierSourcesAfterDeletion(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $directory = $filesystem;
        $name = 'source.php';
        $contents = 'first';
        $url = $directory->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $directory->path() . '/' . $name;
        $collector = new Collector();
        $first = $collector->collect([$path]);
        $name1 = 'source.php';
        $contents1 = 'second';
        $url1 = $directory->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $second = $collector->collect([$path]);
        $path1 = $path;
        $path1 = str_starts_with($path1, '/') ? $path1 : $workingDirectory . '/' . $path1;
        self::assertTrue(unlink('vfs:/' . Path::normalize($path1)));
        self::assertSame('first', $first[0]->contents);
        self::assertSame('second', $second[0]->contents);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCollectNormalizesSourcePaths(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project', null, ['nested' => []]);
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $directory = $filesystem;
        $name = 'nested/source.php';
        $contents = '';
        $url = $directory->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $directory->path() . '/' . $name;
        $sources = (new Collector())->collect(['/' . $directory->path() . '/nested/../nested/./source.php']);
        self::assertSame($path, $sources[0]->path);
        self::assertSame('', $sources[0]->contents);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testCollectRejectsRepeatedPathsAfterNormalization(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $directory = $filesystem;
        $name = 'source.php';
        $contents = '<?php';
        $url = $directory->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $directory->path() . '/' . $name;
        $this->expectException(CollectorException::class);
        $this->expectExceptionMessage('Source paths must be nonempty and unique:');
        (new Collector())->collect([$path, '/' . $directory->path() . '/./source.php']);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    #[DataProvider('providerUnreadableFiles')]
    public function testCollectRejectsWrappersDirectoriesAndMissingFiles(string $path): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $this->expectException(CollectorException::class);
        $this->expectExceptionMessage('Source must be a readable local file:');
        (new Collector())->collect([$path]);
    }
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnreadableFiles(): iterable
    {
        yield 'wrapper' => ['php://memory'];
        yield 'readable local wrapper' => ['file://' . __FILE__];
        yield 'directory' => [__DIR__];
        yield 'missing' => [__DIR__ . '/does-not-exist.php'];
    }
    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
