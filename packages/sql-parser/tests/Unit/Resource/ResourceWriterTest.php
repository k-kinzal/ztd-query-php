<?php

declare(strict_types=1);

namespace Tests\Unit\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\SymbolTable;
use SqlParser\Resource\ResourceWriter;
use SqlParser\Resource\SqlVersion;
use SqlParser\Table\ArrayRows;
use SqlParser\Table\PackedRows;
use SqlParser\Table\ParseTable;
use SqlParser\Table\TableCodec;
use SqlParser\Table\TableFile;

#[CoversClass(ResourceWriter::class)]
#[UsesClass(ArrayRows::class)]
#[UsesClass(PackedRows::class)]
#[UsesClass(ParseTable::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SymbolTable::class)]
#[UsesClass(TableCodec::class)]
#[UsesClass(TableFile::class)]
#[Small]
final class ResourceWriterTest extends TestCase
{
    public function testWriteTable(): void
    {
        $directory = sys_get_temp_dir() . '/sql-parser-writer-' . uniqid();
        $version = new SqlVersion('sqlite', 'sqlite-0.0.0', "{$directory}/tables/t.bin", "{$directory}/keywords/k.php");
        (new ResourceWriter())->writeTable($version, new ParseTable(new SymbolTable(['$end', 'A'], ['$accept']), [], [], new ArrayRows([])));

        self::assertSame(['$end', 'A'], (new TableFile())->load($version->tablePath)->symbols->terminals());
        unlink($version->tablePath);
        rmdir("{$directory}/tables");
        rmdir($directory);
    }

    public function testWriteKeywords(): void
    {
        $directory = sys_get_temp_dir() . '/sql-parser-writer-' . uniqid();
        $version = new SqlVersion('sqlite', 'sqlite-0.0.0', "{$directory}/tables/t.bin", "{$directory}/keywords/k.php");
        (new ResourceWriter())->writeKeywords($version, ['keywords' => ['SELECT' => 'SELECT']], 'parse.y');
        $loaded = require $version->keywordPath;

        self::assertSame(['keywords' => ['SELECT' => 'SELECT']], $loaded);
        self::assertStringContainsString('generated from parse.y', (string) file_get_contents($version->keywordPath));
        unlink($version->keywordPath);
        rmdir("{$directory}/keywords");
        rmdir($directory);
    }

    public function testExport(): void
    {
        $exported = (new ResourceWriter())->export(['keywords' => ['A' => 'A_SYM'], 'name' => 'x'], 0);

        self::assertSame("[\n    'keywords' => [\n        'A' => 'A_SYM',\n    ],\n    'name' => 'x',\n]", $exported);
    }

    public function testEnsureDirectory(): void
    {
        $directory = sys_get_temp_dir() . '/sql-parser-dir-' . uniqid();
        (new ResourceWriter())->ensureDirectory("{$directory}/nested/file.txt");

        self::assertDirectoryExists("{$directory}/nested");
        rmdir("{$directory}/nested");
        rmdir($directory);
    }
}
