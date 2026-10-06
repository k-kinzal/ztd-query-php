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
            ['assets/document-design-v1.2.1.css', 'assets/document-design-v1.2.1.js', 'assets/document-design-LICENSE.txt', 'assets/report.js'],
            array_keys((new ReportAssets())->all()),
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function providerAsset(): array
    {
        return [['assets/document-design-v1.2.1.css'], ['assets/document-design-v1.2.1.js'], ['assets/document-design-LICENSE.txt'], ['assets/report.js']];
    }

    #[DataProvider('providerAsset')]
    public function testAllReadsEveryFileItNames(string $name): void
    {
        self::assertNotSame('', (new ReportAssets())->all()[$name]);
    }

    public function testTheDesignIsTheReleaseTheNoticeNamesAndNothingElse(): void
    {
        $notice = (new ReportAssets())->read('document-design-LICENSE.txt');

        self::assertStringContainsString('doc-ui v1.2.1', $notice);
        self::assertStringContainsString('Commit: 8a9032c7546db6dd9c55e38f15e6c9e388987fab', $notice);
        self::assertStringContainsString('https://k-kinzal.github.io/document-design/v1.2.1/document-design.css', $notice);
        self::assertStringContainsString('https://k-kinzal.github.io/document-design/v1.2.1/document-design.js', $notice);
        self::assertStringContainsString('SHA-256: ' . hash('sha256', (new ReportAssets())->read('document-design-v1.2.1.css')), $notice);
        self::assertStringContainsString('SHA-256: ' . hash('sha256', (new ReportAssets())->read('document-design-v1.2.1.js')), $notice);
        self::assertStringContainsString('MIT License', $notice);
        self::assertStringContainsString('Copyright (c) 2026 k-kinzal', $notice);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerReleaseAsset(): array
    {
        return [
            ['document-design-v1.2.1.css', '17d7832ef632ad4c7101d479b3dbcc64beac0e7417c3ac51a66f99964f0bc918'],
            ['document-design-v1.2.1.js', '777a61377065322ccd15438d53f3de0d0eed180d0e08a8199c95d4eb79ae2d52'],
            ['document-design-v1.0.0.css', '05f312d9faf6de35a0995cfa9434df1cab3a93c836a533307f9e23338a527793'],
            ['document-design-v1.0.0.js', '22e224a51afc3367332c58eb43d693bfd3358e3a578a9115d0886a3758efce2c'],
        ];
    }

    #[DataProvider('providerReleaseAsset')]
    public function testCurrentAndArchivedAssetsRetainTheirPublishedBytes(string $name, string $checksum): void
    {
        $assets = new ReportAssets();

        self::assertSame($checksum, hash('sha256', $assets->read($name)));
        self::assertStringContainsString('SHA-256: ' . $checksum, $assets->read('document-design-LICENSE.txt'));
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
