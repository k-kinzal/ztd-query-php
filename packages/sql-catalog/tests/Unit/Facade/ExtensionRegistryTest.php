<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\ExtensionRegistry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Extension\ExtensionRegistry::class)]
final class ExtensionRegistryTest extends TestCase
{
    public function testWithBuiltinsKeepsThePublicSelection(): void
    {
        self::assertSame(['doctrine', 'laravel', 'mysqli', 'pdo', 'wordpress'], \SqlCatalog\Facade\ExtensionRegistry::withBuiltins()->names());
    }
}
