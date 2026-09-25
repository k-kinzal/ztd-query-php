<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Facade\HtmlReporter::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\SqlCatalog\Reporter\Html\HtmlReporter::class)]
final class HtmlReporterTest extends TestCase
{
    public function testNameKeepsTheDefaultReporterIdentity(): void
    {
        self::assertSame('html', (new \SqlCatalog\Facade\HtmlReporter())->name());
    }
}
