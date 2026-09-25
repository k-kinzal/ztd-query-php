<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\LaravelExtension::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Extension\Laravel\LaravelExtension::class)]
final class LaravelExtensionTest extends TestCase
{
    public function testNameKeepsTheDefaultExtensionIdentity(): void
    {
        self::assertSame('laravel', (new \SqlCatalog\Facade\LaravelExtension())->name());
    }
}
