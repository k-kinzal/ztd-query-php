<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Metadata;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\IndexTarget;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexInspection;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\IndexMetadataLowering;
use SqlSemantics\Platform\Sqlite\Model\Contract\SearchPath;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(IndexMetadataLowering::class)]
#[Small]
final class IndexMetadataLoweringTest extends TestCase
{
    public function testLowerFindsARealIndexBeforeANameMatchingAnotherTablesPrimaryStorage(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $context = $semantics->context([
            'CREATE TABLE main.records (id INT)',
            'CREATE INDEX main.entries ON records(id)',
            'CREATE TABLE archive.entries (key INT PRIMARY KEY, value TEXT) WITHOUT ROWID',
        ]);
        $result = $semantics->analyze('PRAGMA index_xinfo(entries)', $context);

        self::assertInstanceOf(IndexInspection::class, $result);
        self::assertInstanceOf(IndexTarget::class, $result->target);
        self::assertSame('records', $result->target->table->value);
        self::assertSame('PRAGMA "main".index_xinfo("entries")', $result->toString());
    }

    public function testStorageResolvesTheAliasAndIndexNameToTheSamePhysicalCoordinate(): void
    {
        $semantics = new Semantics(searchPath: new SearchPath('main', 'archive'));
        $context = $semantics->context([
            'CREATE TABLE archive.entries (a INT, b TEXT, UNIQUE(b), PRIMARY KEY(a DESC)) WITHOUT ROWID',
        ]);
        $table = $semantics->analyze('PRAGMA index_info(entries)', $context);
        $index = $semantics->analyze('PRAGMA archive.index_info(sqlite_autoindex_entries_2)', $context);

        self::assertInstanceOf(IndexInspection::class, $table);
        self::assertInstanceOf(IndexInspection::class, $index);
        self::assertInstanceOf(IndexTarget::class, $table->target);
        self::assertEquals($table->target, $index->target);
        self::assertSame('PRAGMA "archive".index_info("sqlite_autoindex_entries_2")', $table->toString());
    }
}
