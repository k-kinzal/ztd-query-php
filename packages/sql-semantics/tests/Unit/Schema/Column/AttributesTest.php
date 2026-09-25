<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\Attributes;
use SqlSemantics\Schema\Column\Format;
use SqlSemantics\Schema\Column\Storage;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Attributes::class)]
#[Medium]
final class AttributesTest extends TestCase
{
    public function testAbsentDeclarationsAreNull(): void
    {
        $attributes = new Attributes();
        self::assertNull($attributes->collation);
        self::assertNull($attributes->characterSet);
        self::assertNull($attributes->comment);
        self::assertNull($attributes->visible);
        self::assertNull($attributes->storage);
        self::assertNull($attributes->format);
        self::assertNull($attributes->spatialReferenceId);
        self::assertFalse($attributes->zeroFill);
        self::assertFalse($attributes->binary);
    }

    public function testClassifiesMySqlColumnOptions(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build("CREATE TABLE t(name VARCHAR(10) COLLATE utf8mb4_bin COMMENT 'label' INVISIBLE COLUMN_FORMAT FIXED STORAGE DISK, n INT ZEROFILL)")->tables[0];
        $attributes = $table->columns[0]->attributes;
        self::assertSame(['utf8mb4_bin'], $attributes->collation?->parts);
        self::assertSame('label', $attributes->comment);
        self::assertFalse($attributes->visible);
        self::assertSame(Format::Fixed, $attributes->format);
        self::assertSame(Storage::Disk, $attributes->storage);
        self::assertFalse($attributes->zeroFill);
        self::assertTrue($table->columns[1]->attributes->zeroFill);
    }

    public function testAttributesSerializeBackIntoTheColumnDeclaration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE TABLE t(name VARCHAR(10) COMMENT 'label' INVISIBLE COLUMN_FORMAT FIXED STORAGE DISK)");
        self::assertSame("CREATE TABLE `t`(`name` varchar(10) COMMENT 'label' STORAGE DISK COLUMN_FORMAT FIXED INVISIBLE)", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }


    public function testReadsThePostgreSqlStorageStrategy(): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(c text STORAGE MAIN)')->tables[0]->columns[0];
        self::assertSame(\SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode::Main, $column->attributes->storageStrategy);
        self::assertNull($column->attributes->storage);
    }

    public function testRejectsBothStorageForms(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new Attributes(storage: Storage::Disk, storageStrategy: \SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode::Plain);
    }

    public function testDefaultsToLoadingTheColumnIntoTheSecondaryEngine(): void
    {
        self::assertFalse((new Attributes())->excludedFromSecondaryEngine);
        self::assertTrue((new Attributes(excludedFromSecondaryEngine: true))->excludedFromSecondaryEngine);
    }
}
