<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\ArtifactWriter;
use SqlCatalog\Cli\WriteFailureException;
use SqlCatalog\Core\Reporter\CatalogArtifacts;
use SqlCatalog\Reporter\Html\PageShell;
use SqlCatalog\Reporter\Html\ReportAssets;

#[CoversClass(ArtifactWriter::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(WriteFailureException::class)]
#[UsesClass(PageShell::class)]
#[UsesClass(ReportAssets::class)]
final class ArtifactWriterTest extends TestCase
{
    public function testWriteCreatesTheDirectoryAndTheFiles(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6)) . '/nested';
        $written = (new ArtifactWriter())->write($directory, CatalogArtifacts::one('catalog.json', '{}'));
        self::assertSame([$directory . '/catalog.json'], $written);
        self::assertSame('{}', file_get_contents($written[0]));
        unlink($written[0]);
        rmdir($directory);
        rmdir(dirname($directory));
    }

    public function testPrepareAcceptsADirectoryThatIsAlreadyThere(): void
    {
        (new ArtifactWriter())->prepare(sys_get_temp_dir());
        self::assertDirectoryIsWritable(sys_get_temp_dir());
    }

    public function testNewReportAssetsPreserveArchivedPagesAndVersionedAssets(): void
    {
        $directory = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        $assets = new ReportAssets();
        $archived = [
            'archived.html' => '<link rel="stylesheet" href="assets/document-design-v1.0.0.css"><script src="assets/document-design-v1.0.0.js"></script><p>Archived report</p>',
            'assets/document-design-v1.0.0.css' => $assets->read('document-design-v1.0.0.css'),
            'assets/document-design-v1.0.0.js' => $assets->read('document-design-v1.0.0.js'),
        ];
        $writer = new ArtifactWriter();
        try {
            $writer->write($directory, new CatalogArtifacts($archived));
            $writer->write($directory, new CatalogArtifacts($assets->all()));

            self::assertSame($archived['archived.html'], file_get_contents($directory . '/archived.html'));
            self::assertSame($archived['assets/document-design-v1.0.0.css'], file_get_contents($directory . '/assets/document-design-v1.0.0.css'));
            self::assertSame($archived['assets/document-design-v1.0.0.js'], file_get_contents($directory . '/assets/document-design-v1.0.0.js'));
            self::assertSame($assets->read('document-design-v1.2.1.css'), file_get_contents($directory . '/' . PageShell::DESIGN_STYLE));
            self::assertSame($assets->read('document-design-v1.2.1.js'), file_get_contents($directory . '/' . PageShell::DESIGN_SCRIPT));
        } finally {
            unlink($directory . '/archived.html');
            unlink($directory . '/assets/document-design-v1.0.0.css');
            unlink($directory . '/assets/document-design-v1.0.0.js');
            unlink($directory . '/' . PageShell::DESIGN_STYLE);
            unlink($directory . '/' . PageShell::DESIGN_SCRIPT);
            unlink($directory . '/' . PageShell::DESIGN_LICENSE);
            unlink($directory . '/' . PageShell::SCRIPT);
            rmdir($directory . '/assets');
            rmdir($directory);
        }
    }

    public function testWriteRefusesADirectoryItCannotCreate(): void
    {
        $blocking = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        file_put_contents($blocking, 'not a directory');
        $this->expectException(WriteFailureException::class);
        $this->expectExceptionMessage('a file of that name is in the way');
        (new ArtifactWriter())->write($blocking, CatalogArtifacts::one('a.json', '{}'));
    }
}
