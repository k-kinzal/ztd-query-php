<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableOptionReset;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Schema\Table\MergeInsertMethod;
use SqlSemantics\Schema\Table\MySqlProperties;
use SqlSemantics\Schema\Table\TableStorage;
use SqlSemantics\Serialization\Definition\MySqlTable\TableOptionWriter;

#[CoversClass(TableOptionWriter::class)]
final class TableOptionWriterTest extends TestCase
{
    public function testWriteWritesStorageMergeAndSizeOptions(): void
    {
        $options = new MySqlProperties(storage: TableStorage::Disk, secondaryEngine: 'rapid', insertMethod: MergeInsertMethod::Last, union: [new QualifiedName(['a'])], startTransaction: true, autoextendSize: 64);
        self::assertSame('STORAGE DISK SECONDARY_ENGINE = `rapid` INSERT_METHOD = LAST UNION =(`a`) AUTOEXTEND_SIZE = 64 START TRANSACTION', TableOptionWriter::write($options)->toString());
    }

    public function testResetsWritesDefaultAndNull(): void
    {
        self::assertSame('STATS_PERSISTENT = DEFAULT SECONDARY_ENGINE = NULL', TableOptionWriter::resets([TableOptionReset::StatsPersistent, TableOptionReset::SecondaryEngine])->toString());
    }
}
