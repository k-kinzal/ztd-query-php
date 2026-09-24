<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Execution;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\MySql\ImportHistogramStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Execution\Histograms;

#[CoversClass(Histograms::class)]
#[Medium]
final class HistogramsTest extends TestCase
{
    public function testWriteKeepsImportedDataAsOneTextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind("ANALYZE TABLE t UPDATE HISTOGRAM ON id USING DATA 'a''b; DROP TABLE t'");
        self::assertInstanceOf(ImportHistogramStatement::class, $statement);
        self::assertSame("ANALYZE TABLE `t` UPDATE HISTOGRAM ON `id` USING DATA 'a''b; DROP TABLE t'", Histograms::write($statement)->toString());
    }

    #[TestWith(['mysql-8.4.7', 'ANALYZE NO_WRITE_TO_BINLOG TABLE t UPDATE HISTOGRAM ON a, b WITH 8 BUCKETS', \SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement::class, 'ANALYZE NO_WRITE_TO_BINLOG TABLE `t` UPDATE HISTOGRAM ON `a`, `b` WITH 8 BUCKETS'])]
    #[TestWith(['mysql-8.4.7', 'ANALYZE TABLE t UPDATE HISTOGRAM ON a', \SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement::class, 'ANALYZE TABLE `t` UPDATE HISTOGRAM ON `a`'])]
    #[TestWith(['mysql-8.4.7', 'ANALYZE TABLE t DROP HISTOGRAM ON a, b', \SqlSemantics\Model\Statement\Maintenance\MySql\DropHistogramStatement::class, 'ANALYZE TABLE `t` DROP HISTOGRAM ON `a`, `b`'])]
    #[TestWith(['mysql-8.4.7', 'ANALYZE TABLE t UPDATE HISTOGRAM ON a WITH 4 BUCKETS AUTO UPDATE', \SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement::class, 'ANALYZE TABLE `t` UPDATE HISTOGRAM ON `a` WITH 4 BUCKETS AUTO UPDATE'])]
    #[TestWith(['mysql-8.4.7', 'ANALYZE TABLE t UPDATE HISTOGRAM ON a MANUAL UPDATE', \SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement::class, 'ANALYZE TABLE `t` UPDATE HISTOGRAM ON `a` MANUAL UPDATE'])]
    public function testWriteSpellsSamplingAndRemovalRequests(string $version, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql);
        self::assertTrue($statement instanceof \SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement || $statement instanceof \SqlSemantics\Model\Statement\Maintenance\MySql\DropHistogramStatement);
        self::assertSame([$class, $expected], [$statement::class, Histograms::write($statement)->toString()]);
    }
}
