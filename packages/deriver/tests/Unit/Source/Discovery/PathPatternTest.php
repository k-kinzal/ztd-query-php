<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Discovery;

use Deriver\Exception\InvalidInputException;
use Deriver\Source\Discovery\PathPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathPattern::class)]
#[UsesClass(InvalidInputException::class)]
#[Small]
final class PathPatternTest extends TestCase
{
    public function testMatchesAnyAcceptsOneMatchAndRejectsEmptyAlternatives(): void
    {
        $patterns = [new PathPattern('src/*.php'), new PathPattern('tests/**/*.php')];
        self::assertTrue(PathPattern::matchesAny($patterns, 'tests/deep/Test.php'));
        self::assertFalse(PathPattern::matchesAny($patterns, 'vendor/File.php'));
        self::assertFalse(PathPattern::matchesAny([], 'src/File.php'));
    }

    #[DataProvider('providerPatterns')]
    public function testMatchesCompleteRelativePaths(string $pattern, string $path, bool $expected): void
    {
        self::assertSame($expected, (new PathPattern($pattern))->matches($path));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function providerPatterns(): iterable
    {
        yield 'zero directories' => ['**/*.php', 'a.php', true];
        yield 'multiple directories' => ['**/*.php', 'a/b/c.php', true];
        yield 'one component' => ['*.php', 'a/b.php', false];
        yield 'any extension' => ['src/**', 'src/deep/a.inc', true];
        yield 'prunable directory' => ['**/vendor/**', 'src/vendor/', true];
        yield 'root anchored' => ['vendor/**', 'src/vendor/a.php', false];
        yield 'suffix required' => ['**/*.php', 'a.php.txt', false];
        yield 'case sensitive' => ['**/*.php', 'A.PHP', false];
        yield 'literal punctuation' => ['a[1]+(x).php', 'a[1]+(x).php', true];
        yield 'no regex interpretation' => ['a+.php', 'aa.php', false];
        yield 'dot prefix and separators' => ['././src\\*.php', 'src/a.php', true];
        yield 'question does not cross directories' => ['a?b', 'a/b', false];
    }

    #[DataProvider('providerInvalidPatterns')]
    public function testRejectsPatternsOutsideTheRoot(string $pattern): void
    {
        $this->expectException(InvalidInputException::class);
        new PathPattern($pattern);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function providerInvalidPatterns(): iterable
    {
        foreach (['', './', '/src/*.php', '../*.php', 'src/../../*', 'php://*', 'C:\\src\\*'] as $pattern) {
            yield [$pattern];
        }
    }
}
