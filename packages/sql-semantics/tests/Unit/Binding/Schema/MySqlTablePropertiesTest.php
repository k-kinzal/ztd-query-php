<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Schema\MySqlTableProperties;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Partition\HashPartitioning;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Table\MergeInsertMethod;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\RowFormat;
use SqlSemantics\Schema\Table\TableStorage;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlTableProperties::class)]
#[Medium]
final class MySqlTablePropertiesTest extends TestCase
{
    public function testBindReadsOptionsFromADeclaration(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) ENGINE=InnoDB PACK_KEYS=1 STATS_PERSISTENT=DEFAULT STATS_AUTO_RECALC := 0 AUTO_INCREMENT=0100 ROW_FORMAT=DYNAMIC')->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertSame('InnoDB', $properties->engine);
        self::assertTrue($properties->packKeys);
        self::assertNull($properties->statsPersistent);
        self::assertFalse($properties->statsAutoRecalc);
        self::assertSame(100, $properties->autoIncrement);
        self::assertSame(RowFormat::Dynamic, $properties->rowFormat);
    }

    public function testBindReadsStorageMergeAndSizeOptions(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) TABLESPACE ts STORAGE MEMORY UNION=(a, db.b) INSERT_METHOD=FIRST AUTOEXTEND_SIZE=4M SECONDARY_ENGINE=rapid TABLE_CHECKSUM=1')->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertSame(TableStorage::Memory, $properties->storage);
        self::assertSame([['a'], ['db', 'b']], array_map(static fn ($table): array => $table->parts, $properties->union ?? []));
        self::assertSame(MergeInsertMethod::First, $properties->insertMethod);
        self::assertSame(4194304, $properties->autoextendSize);
        self::assertSame('rapid', $properties->secondaryEngine);
        self::assertSame(1, $properties->checksum);
    }

    public function testBindReadsThePartitioning(): void
    {
        $properties = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT) PARTITION BY HASH (id) PARTITIONS 4')->tables[0]->properties;
        self::assertInstanceOf(MySqlProperties::class, $properties);
        self::assertInstanceOf(HashPartitioning::class, $properties->partitioning?->function);
        self::assertSame(4, $properties->partitioning->partitionCount);
    }

    public function testBindDiagnosesASwitchOutsideZeroAndOne(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TableOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t(id INT) PACK_KEYS = 2');
    }
}
