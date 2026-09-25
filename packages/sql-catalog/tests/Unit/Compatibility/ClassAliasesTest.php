<?php

declare(strict_types=1);

namespace Tests\Unit\Compatibility;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\AnalysisOptions::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\ExtensionRegistry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\ReporterRegistry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Extension\ExtensionRegistry::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Core\Reporter\ReporterRegistry::class)]
final class ClassAliasesTest extends TestCase
{
    public function testLegacyRegistriesAndOptionsKeepTheirBehavior(): void
    {
        $options = new \SqlCatalog\AnalysisOptions(['pdo']);
        self::assertSame(['pdo'], $options->extensions);
        self::assertSame(['pdo', 'mysqli'], \SqlCatalog\Extension\ExtensionRegistry::withBuiltins()->defaultNames());
        self::assertSame(['html', 'json', 'text'], \SqlCatalog\Reporter\ReporterRegistry::withBuiltins()->names());
    }
}
