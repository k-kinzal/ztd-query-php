<?php

declare(strict_types=1);

namespace Tests\Unit\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\PackedRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableCodec;
use SqlParser\Table\TableFile;
use SqlParser\Table\TableRule;

#[CoversClass(TableFile::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(PackedRows::class)]
#[UsesClass(ParseTable::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(TableCodec::class)]
#[UsesClass(TableRule::class)]
#[Small]
final class TableFileTest extends TestCase
{
    public function testSaveAndLoadRoundTrip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-table');
        self::assertIsString($path);
        $file = new TableFile();
        $file->save(new ParseTable(new SymbolTable(['$end', 'A'], ['$accept']), [new TableRule(2, 1, 0)], [-1], new ArrayRows([[1 => 1]])), $path);
        $loaded = $file->load($path);

        self::assertSame(['$end', 'A'], $loaded->symbols->terminals());
        self::assertSame([1 => 1], $loaded->rows->row(0));
        self::assertSame($loaded, $file->load($path));
        unlink($path);
    }

    public function testLoadRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        (new TableFile())->load(sys_get_temp_dir() . '/sql-parser-missing-' . uniqid() . '.bin');
    }

    public function testLoadRejectsAFileThatIsNotDeflated(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-plain');
        self::assertIsString($path);
        file_put_contents($path, 'plain text');

        $this->expectException(RuntimeException::class);

        try {
            (new TableFile())->load($path);
        } finally {
            unlink($path);
        }
    }

    public function testInflate(): void
    {
        $file = new TableFile();

        self::assertSame('abc', $file->inflate((string) gzdeflate('abc')));
        self::assertNull($file->inflate('plain text'));
    }

    public function testForget(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sql-parser-table');
        self::assertIsString($path);
        $file = new TableFile();
        $file->save(new ParseTable(new SymbolTable(['$end'], ['$accept']), [], [], new ArrayRows([])), $path);
        $first = $file->load($path);
        TableFile::forget();

        self::assertNotSame($first, $file->load($path));
        unlink($path);
    }
}
