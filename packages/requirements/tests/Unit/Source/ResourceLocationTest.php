<?php

declare(strict_types=1);

namespace Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Source\ResourceLocation;
use RuntimeException;

#[CoversClass(ResourceLocation::class)]
#[Small]
final class ResourceLocationTest extends TestCase
{
    #[DataProvider('providerResolve')]
    public function testResolve(string $uri, string $expected): void
    {
        self::assertSame($expected, (new ResourceLocation())->resolve($uri, '/project/config'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerResolve(): array
    {
        return [
            'relative path' => ['docs/manual.html', '/project/config/docs/manual.html'],
            'parent path' => ['../manual.html', '/project/config/../manual.html'],
            'absolute path' => ['/srv/manual.html', '/srv/manual.html'],
            'https' => ['https://example.org/manual.html', 'https://example.org/manual.html'],
            'http' => ['http://example.org/manual.html', 'http://example.org/manual.html'],
        ];
    }

    #[DataProvider('providerResolveOtherScheme')]
    public function testResolveRejectsOtherSchemes(string $uri): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Built-in sources accept HTTP(S) URLs or local paths only.');
        (new ResourceLocation())->resolve($uri, '/project/config');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providerResolveOtherScheme(): array
    {
        return [
            'file' => ['file:///etc/passwd'],
            'ftp' => ['ftp://example.org/manual.html'],
            'scheme inside an absolute path' => ['/srv/ftp://manual.html'],
            'http later in the uri' => ['php://filter/http://example.org'],
            'uppercase https' => ['HTTPS://example.org/manual.html'],
        ];
    }
}
