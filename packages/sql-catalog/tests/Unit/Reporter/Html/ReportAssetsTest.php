<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportAssets;

#[CoversClass(ReportAssets::class)]
#[UsesClass(PageShell::class)]
final class ReportAssetsTest extends TestCase
{
    public function testAllCarriesTheStylesheetAndTheScript(): void
    {
        self::assertSame(
            ['assets/report.css', 'assets/report.js'],
            array_keys((new ReportAssets())->all()),
        );
    }

    public function testReadCarriesThePackagesOwnResource(): void
    {
        self::assertStringContainsString('--kind-select', (new ReportAssets())->read('report.css'));
    }

    public function testReadIsEmptyForSomethingThePackageDoesNotCarry(): void
    {
        self::assertSame('', (new ReportAssets())->read('nothing-here.css'));
    }
}
