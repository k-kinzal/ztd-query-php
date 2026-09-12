<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\TableDefinitionInput as Subject;

#[CoversClass(Subject::class)]
final class TableDefinitionInputTest extends TestCase
{
    public function testWithoutPartitioningKeepsColumnMetadataBeforeAsValues(): void
    {
        $sql = 'CREATE TABLE t (value VARBINARY(1)) STATS_SAMPLE_PAGES 1 PARTITION BY KEY () REPLACE AS VALUES ROW (NULL)';
        self::assertSame('CREATE TABLE t (value VARBINARY(1)) STATS_SAMPLE_PAGES 1', (new Subject())->withoutPartitioning($sql));
    }

    public function testWithoutPartitioningPreservesQuotedKeywordsAndNestedTypes(): void
    {
        $sql = "CREATE TABLE t (note TEXT DEFAULT 'PARTITION BY (x)', amount DECIMAL(8, 2))";
        self::assertSame($sql, (new Subject())->withoutPartitioning($sql));
    }

    public function testWithoutPartitioningLeavesStatementsWithoutAColumnListAlone(): void
    {
        self::assertSame('CREATE TABLE t LIKE other', (new Subject())->withoutPartitioning('CREATE TABLE t LIKE other'));
    }

    public function testWithoutPartitioningPreservesMultibyteIdentifiers(): void
    {
        $sql = 'CREATE TABLE `猫` (`名前` INT) PARTITION BY HASH (`名前`) PARTITIONS 2';
        self::assertSame('CREATE TABLE `猫` (`名前` INT)', (new Subject())->withoutPartitioning($sql));
    }

    public function testWithoutPartitioningPreservesCommentsAndLineBreaks(): void
    {
        $sql = 'CREATE TABLE t (id INT) /* PARTITION BY (x) */ ENGINE=InnoDB';
        self::assertSame($sql, (new Subject())->withoutPartitioning($sql));
    }
}
