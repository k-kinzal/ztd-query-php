<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Cli\ArtifactWriter;
use SqlCatalog\Cli\WriteFailureException;
use SqlCatalog\Reporter\CatalogArtifacts;

#[CoversClass(ArtifactWriter::class)]
#[UsesClass(CatalogArtifacts::class)]
#[UsesClass(WriteFailureException::class)]
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

    public function testWriteRefusesADirectoryItCannotCreate(): void
    {
        $blocking = sys_get_temp_dir() . '/sql-catalog-test-' . bin2hex(random_bytes(6));
        file_put_contents($blocking, 'not a directory');
        $this->expectException(WriteFailureException::class);
        $this->expectExceptionMessage('a file of that name is in the way');
        (new ArtifactWriter())->write($blocking, CatalogArtifacts::one('a.json', '{}'));
    }
}
