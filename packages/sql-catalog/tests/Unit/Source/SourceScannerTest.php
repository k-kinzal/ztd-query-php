<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Source\SourceFile;
use SqlCatalog\Source\SourceScanException;
use SqlCatalog\Source\SourceScanner;

#[CoversClass(SourceScanner::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceScanException::class)]
final class SourceScannerTest extends TestCase
{
    public function testScanFindsThePhpFilesUnderTheCorpus(): void
    {
        $root = dirname(__DIR__, 3);
        $files = (new SourceScanner($root))->scan([$root . '/corpus/app']);
        $paths = array_map(static fn (SourceFile $file): string => $file->path, $files);
        self::assertContains('corpus/app/Plain.php', $paths);
        self::assertStringStartsWith('<?php', $files[0]->code);
    }

    public function testScanReportsPathsInAStableOrder(): void
    {
        $root = dirname(__DIR__, 3);
        $scanner = new SourceScanner($root);
        self::assertSame(
            array_map(static fn (SourceFile $file): string => $file->path, $scanner->scan([$root . '/corpus/app'])),
            array_map(static fn (SourceFile $file): string => $file->path, $scanner->scan([$root . '/corpus/app'])),
        );
    }

    public function testScanOneReadsASingleFile(): void
    {
        $root = dirname(__DIR__, 3);
        $files = (new SourceScanner($root))->scanOne($root . '/corpus/app/Plain.php');
        self::assertCount(1, $files);
        self::assertSame('corpus/app/Plain.php', $files[0]->path);
    }

    public function testScanOneRefusesAPathThatIsNotThere(): void
    {
        $this->expectException(SourceScanException::class);
        (new SourceScanner('.'))->scanOne('/definitely/not/here');
    }

    public function testWalkSkipsTheDirectoriesAScanNeverWants(): void
    {
        $root = dirname(__DIR__, 3);
        $paths = (new SourceScanner($root))->walk($root . '/corpus');
        self::assertNotEmpty($paths);
        self::assertSame([], array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_contains($path, '/vendor/'),
        )));
    }

    public function testReadNamesTheFileRelativeToTheRoot(): void
    {
        $root = dirname(__DIR__, 3);
        self::assertSame('corpus/app/Plain.php', (new SourceScanner($root))->read($root . '/corpus/app/Plain.php')?->path);
    }

    public function testReadSkipsAnExcludedFile(): void
    {
        $root = dirname(__DIR__, 3);
        self::assertNull((new SourceScanner($root, ['corpus/*']))->read($root . '/corpus/app/Plain.php'));
    }

    public function testIsExcludedAcceptsAPatternOrADirectory(): void
    {
        $scanner = new SourceScanner('.', ['vendor', 'src/Generated/*.php']);
        self::assertTrue($scanner->isExcluded('vendor/a.php'));
        self::assertTrue($scanner->isExcluded('src/Generated/a.php'));
        self::assertFalse($scanner->isExcluded('src/a.php'));
    }

    public function testRelativeLeavesAPathOutsideTheRootAlone(): void
    {
        self::assertSame('/definitely/not/here', (new SourceScanner('.'))->relative('/definitely/not/here'));
    }
}
