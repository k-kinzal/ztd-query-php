<?php

declare(strict_types=1);

namespace Tests\Unit\Config\Markdown;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Markdown\LocalPath;

#[CoversClass(LocalPath::class)]
#[Small]
final class LocalPathTest extends TestCase
{
    #[DataProvider('providerNormalize')]
    public function testNormalizeResolvesDotsAndSeparators(string $path, string $expected): void
    {
        self::assertSame($expected, LocalPath::normalize($path));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerNormalize(): array
    {
        return [
            'plain' => ['a/b.md', 'a/b.md'],
            'current directory and repeated separators' => ['a/./b//c/', 'a/b/c'],
            'parent directory' => ['/project/definitions/../source.html', 'project/source.html'],
            'backslashes' => ['a\\b\\..\\c', 'a/c'],
            'parent above the start' => ['../a', 'a'],
            'empty' => ['', ''],
            'only separators' => ['/./', ''],
        ];
    }
}
