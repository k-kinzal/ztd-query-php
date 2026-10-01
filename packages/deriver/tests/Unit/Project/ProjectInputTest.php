<?php

declare(strict_types=1);

namespace Tests\Unit\Project;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProjectInput::class)]
#[UsesClass(SourceFile::class)]
#[Small]
final class ProjectInputTest extends TestCase
{
    public function testRetainsSourceIdentityAndCallerOrderWithoutExecutingContents(): void
    {
        $first = new SourceFile('z.php', '<?php throw new RuntimeException("must not execute");');
        $second = new SourceFile('a.php', '<?php', true);
        $input = new ProjectInput([$first,$second]);
        self::assertSame([$first,$second], $input->files);
    }
    public function testFromFilesReadsContentsWithoutLoadingDeclarations(): void
    {
        $input = ProjectInput::fromFiles([__FILE__]);
        self::assertCount(1, $input->files);
        self::assertStringContainsString('class ProjectInputTest', $input->files[0]->contents);
    }

    public function testFromDirectoryCapturesRelativePaths(): void
    {
        $input = ProjectInput::fromDirectory(__DIR__);
        self::assertContains('ProjectInputTest.php', array_map(static fn (SourceFile $file): string => $file->path, $input->files));
    }

    public function testNormalizeCollapsesDotComponents(): void
    {
        self::assertSame('src/App.php', ProjectInput::normalize('src/./old/../App.php'));
    }


    #[DataProvider('providerNormalizedPaths')]
    public function testNormalizeRetainsRootAndUnresolvedParentComponents(string $path, string $expected): void
    {
        self::assertSame($expected, ProjectInput::normalize($path));
    }

    /**
     * @return iterable<string,array{string,string}>
     */
    public static function providerNormalizedPaths(): iterable
    {
        yield 'empty' => ['',''];
        yield 'dot' => ['.',''];
        yield 'root' => ['/','/'];
        yield 'repeated slashes' => ['/src//./File.php','/src/File.php'];
        yield 'windows separators' => ['C:\\src\\old\\..\\File.php','C:/src/File.php'];
        yield 'relative parent' => ['../src/File.php','../src/File.php'];
        yield 'repeated parent' => ['../../src','../../src'];
        yield 'parent after collapse' => ['src/../../other','../other'];
        yield 'trailing separator' => ['src///','src'];
        yield 'parent spelling in filename' => ['src/..file.php','src/..file.php'];
        yield 'relative collapse' => ['src/../File.php','File.php'];
    }

    /**
     * @param list<SourceFile> $files Captured source descriptions
     */
    #[DataProvider('providerInvalidSourcePaths')]
    public function testRejectsEmptyAndRepeatedNormalizedSourceIdentities(array $files): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Source paths must be nonempty and unique:');
        new ProjectInput($files);
    }

    /**
     * @return iterable<string,array{list<SourceFile>}>
     */
    public static function providerInvalidSourcePaths(): iterable
    {
        yield 'empty' => [[new SourceFile('', '')]];
        yield 'normalized empty' => [[new SourceFile('./', '')]];
        yield 'repeated' => [[new SourceFile('src/a.php', 'first'),new SourceFile('src/a.php', 'second')]];
        yield 'separator alias' => [[new SourceFile('src/a.php', 'first'),new SourceFile('src\\a.php', 'second')]];
        yield 'dot alias' => [[new SourceFile('src/a.php', 'first'),new SourceFile('src/old/../a.php', 'second')]];
    }

    #[DataProvider('providerUnreadableFiles')]
    public function testFromFilesRejectsWrappersDirectoriesAndMissingFiles(string $path): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Source must be a readable local file:');
        ProjectInput::fromFiles([$path]);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerUnreadableFiles(): iterable
    {
        yield 'wrapper' => ['php://memory'];
        yield 'readable local wrapper' => ['file://' . __FILE__];
        yield 'directory' => [__DIR__];
        yield 'missing' => [__DIR__.'/does-not-exist.php'];
    }

    #[DataProvider('providerUnreadableDirectories')]
    public function testFromDirectoryRejectsWrappersFilesAndMissingRoots(string $path): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Source root must be a readable local directory.');
        ProjectInput::fromDirectory($path);
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerUnreadableDirectories(): iterable
    {
        yield 'wrapper' => ['file://'.__DIR__];
        yield 'file' => [__FILE__];
        yield 'missing' => [__DIR__.'/does-not-exist'];
    }

    public function testFromFilesKeepsCapturedBytesAfterTheFileHasChanged(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'deriver-source-');
        self::assertIsString($path);
        $contents = '<?php throw new RuntimeException("source must not execute");';
        self::assertSame(strlen($contents), file_put_contents($path, $contents));
        $input = ProjectInput::fromFiles([$path]);
        self::assertTrue(unlink($path));
        self::assertCount(1, $input->files);
        self::assertSame($contents, $input->files[0]->contents);
        self::assertSame(ProjectInput::normalize($path), $input->files[0]->path);
        self::assertFalse($input->files[0]->declarationsOnly);
    }

    public function testFromDirectorySortsPhpFilesAndPrunesExcludedAndLinkedEntries(): void
    {
        $root = tempnam(sys_get_temp_dir(), 'deriver-tree-');
        self::assertIsString($root);
        self::assertTrue(unlink($root));
        self::assertTrue(mkdir($root));
        self::assertTrue(mkdir($root.'/nested'));
        self::assertTrue(mkdir($root.'/vendor'));
        self::assertSame(5, file_put_contents($root.'/z.php', '<?php'));
        self::assertSame(5, file_put_contents($root.'/nested/a.PHP', '<?php'));
        self::assertSame(5, file_put_contents($root.'/ignored.txt', '<?php'));
        self::assertSame(5, file_put_contents($root.'/vendor/excluded.php', '<?php'));
        self::assertTrue(symlink($root.'/nested', $root.'/linked'));
        self::assertTrue(symlink($root.'/z.php', $root.'/linked.php'));
        $input = ProjectInput::fromDirectory($root.'/');
        $custom = ProjectInput::fromDirectory($root, ['nested','z.php']);
        self::assertTrue(unlink($root.'/linked.php'));
        self::assertTrue(unlink($root.'/linked'));
        self::assertTrue(unlink($root.'/z.php'));
        self::assertTrue(unlink($root.'/nested/a.PHP'));
        self::assertTrue(unlink($root.'/ignored.txt'));
        self::assertTrue(unlink($root.'/vendor/excluded.php'));
        self::assertTrue(rmdir($root.'/nested'));
        self::assertTrue(rmdir($root.'/vendor'));
        self::assertTrue(rmdir($root));
        self::assertSame(['nested/a.PHP','z.php'], array_column($input->files, 'path'));
        self::assertSame(['<?php','<?php'], array_column($input->files, 'contents'));
        self::assertSame(['vendor/excluded.php'], array_column($custom->files, 'path'));
    }

    #[DataProvider('providerCurrentDirectoryRoots')]
    public function testFromDirectoryPreservesCompleteFileNamesForTheCurrentDirectory(string $directory): void
    {
        $previous = getcwd();
        self::assertIsString($previous);
        $root = tempnam(sys_get_temp_dir(), 'deriver-relative-');
        self::assertIsString($root);
        self::assertTrue(unlink($root));
        self::assertTrue(mkdir($root));
        self::assertSame(5, file_put_contents($root.'/first.php', '<?php'));
        self::assertSame(5, file_put_contents($root.'/second.php', '<?php'));
        self::assertTrue(chdir($root));
        $input = ProjectInput::fromDirectory($directory);
        self::assertTrue(chdir($previous));
        self::assertTrue(unlink($root.'/first.php'));
        self::assertTrue(unlink($root.'/second.php'));
        self::assertTrue(rmdir($root));
        self::assertSame(['first.php','second.php'], array_column($input->files, 'path'));
    }

    /**
     * @return iterable<string,array{string}>
     */
    public static function providerCurrentDirectoryRoots(): iterable
    {
        yield 'dot' => ['.'];
        yield 'dot slash' => ['./'];
        yield 'repeated dot' => ['././'];
    }
}
