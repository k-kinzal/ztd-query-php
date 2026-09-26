<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Source\SourceFile;
use SqlCatalog\Core\Source\SourceScanException;
use SqlCatalog\Core\Source\SourceScanner;

#[CoversClass(SourceScanner::class)]
#[UsesClass(SourceFile::class)]
#[UsesClass(SourceScanException::class)]
final class SourceScannerTest extends TestCase
{
    public function testScanFindsThePhpFilesUnderADirectory(): void
    {
        $root = dirname(__DIR__, 4);
        $files = (new SourceScanner($root))->scan([$root . '/src']);
        $paths = array_map(static fn (SourceFile $file): string => $file->path, $files);
        self::assertContains('src/Facade/Analyzer.php', $paths);
        self::assertStringStartsWith('<?php', $files[0]->code);
    }

    public function testScanReportsPathsInAStableOrder(): void
    {
        $root = dirname(__DIR__, 4);
        $scanner = new SourceScanner($root);
        self::assertSame(
            array_map(static fn (SourceFile $file): string => $file->path, $scanner->scan([$root . '/src'])),
            array_map(static fn (SourceFile $file): string => $file->path, $scanner->scan([$root . '/src'])),
        );
    }

    public function testScanOneReadsASingleFile(): void
    {
        $root = dirname(__DIR__, 4);
        $files = (new SourceScanner($root))->scanOne($root . '/src/Facade/Analyzer.php');
        self::assertCount(1, $files);
        self::assertSame('src/Facade/Analyzer.php', $files[0]->path);
    }

    public function testScanOneRefusesAPathThatIsNotThere(): void
    {
        $this->expectException(SourceScanException::class);
        (new SourceScanner('.'))->scanOne('/definitely/not/here');
    }

    public function testWalkSkipsTheDirectoriesAScanNeverWants(): void
    {
        $root = dirname(__DIR__, 4);
        $paths = (new SourceScanner($root))->walk($root . '/src');
        self::assertNotEmpty($paths);
        self::assertSame([], array_values(array_filter(
            $paths,
            static fn (string $path): bool => str_contains($path, '/vendor/'),
        )));
    }

    public function testReadNamesTheFileRelativeToTheRoot(): void
    {
        $root = dirname(__DIR__, 4);
        self::assertSame('src/Facade/Analyzer.php', (new SourceScanner($root))->read($root . '/src/Facade/Analyzer.php')?->path);
    }

    public function testReadSkipsAnExcludedFile(): void
    {
        $root = dirname(__DIR__, 4);
        self::assertNull((new SourceScanner($root, ['src/*']))->read($root . '/src/Facade/Analyzer.php'));
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
