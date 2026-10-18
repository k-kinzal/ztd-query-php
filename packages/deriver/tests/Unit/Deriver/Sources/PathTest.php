<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Sources;

use Deriver\Deriver\Sources\Path;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
#[Small]
final class PathTest extends TestCase
{
    #[DataProvider('providerNormalizedPaths')]
    public function testNormalizeRetainsRootAndUnresolvedParentComponents(string $path, string $expected): void
    {
        self::assertSame($expected, Path::normalize($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerNormalizedPaths(): iterable
    {
        yield 'dot components' => ['src/./old/../App.php', 'src/App.php'];
        yield 'empty' => ['', ''];
        yield 'dot' => ['.', ''];
        yield 'root' => ['/', '/'];
        yield 'repeated slashes' => ['/src//./File.php', '/src/File.php'];
        yield 'windows separators' => ['C:\\src\\old\\..\\File.php', 'C:/src/File.php'];
        yield 'relative parent' => ['../src/File.php', '../src/File.php'];
        yield 'repeated parent' => ['../../src', '../../src'];
        yield 'parent after collapse' => ['src/../../other', '../other'];
        yield 'trailing separator' => ['src///', 'src'];
        yield 'parent spelling in filename' => ['src/..file.php', 'src/..file.php'];
        yield 'relative collapse' => ['src/../File.php', 'File.php'];
    }
}
