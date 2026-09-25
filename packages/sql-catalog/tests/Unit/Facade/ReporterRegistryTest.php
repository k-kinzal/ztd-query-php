<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\ReporterRegistry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Reporter\ReporterRegistry::class)]
final class ReporterRegistryTest extends TestCase
{
    public function testWithBuiltinsKeepsThePublicSelection(): void
    {
        self::assertSame(['html', 'json', 'text'], \SqlCatalog\Facade\ReporterRegistry::withBuiltins()->names());
    }
}
