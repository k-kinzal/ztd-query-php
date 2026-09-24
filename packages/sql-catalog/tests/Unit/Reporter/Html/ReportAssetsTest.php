<?php

declare(strict_types=1);

namespace Tests\Unit\Reporter\Html;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportAssets;

#[CoversClass(ReportAssets::class)]
#[UsesClass(PageShell::class)]
final class ReportAssetsTest extends TestCase
{
    public function testAllCarriesTheDesignItsNoticeAndTheReportsOwnFiles(): void
    {
        self::assertSame(
            ['assets/document-design-v1.0.0.css', 'assets/document-design-v1.0.0.js', 'assets/document-design-LICENSE.txt', 'assets/report.js', 'assets/report.css'],
            array_keys((new ReportAssets())->all()),
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function providerAsset(): array
    {
        return [['assets/document-design-v1.0.0.css'], ['assets/document-design-v1.0.0.js'], ['assets/document-design-LICENSE.txt'], ['assets/report.js'], ['assets/report.css']];
    }

    #[DataProvider('providerAsset')]
    public function testAllReadsEveryFileItNames(string $name): void
    {
        self::assertNotSame('', (new ReportAssets())->all()[$name]);
    }

    public function testTheDesignIsTheReleaseTheNoticeNamesAndNothingElse(): void
    {
        $notice = (new ReportAssets())->read('document-design-LICENSE.txt');

        self::assertStringContainsString('doc-ui v1.0.0', $notice);
        self::assertStringContainsString('SHA-256: ' . hash('sha256', (new ReportAssets())->read('document-design-v1.0.0.css')), $notice);
        self::assertStringContainsString('SHA-256: ' . hash('sha256', (new ReportAssets())->read('document-design-v1.0.0.js')), $notice);
        self::assertStringContainsString('MIT License', $notice);
    }

    public function testReadCarriesThePackagesOwnResource(): void
    {
        self::assertStringContainsString('window.ddSearch', (new ReportAssets())->read('report.js'));
    }

    public function testReadIsEmptyForSomethingThePackageDoesNotCarry(): void
    {
        self::assertSame('', (new ReportAssets())->read('nothing-here.css'));
    }
}
