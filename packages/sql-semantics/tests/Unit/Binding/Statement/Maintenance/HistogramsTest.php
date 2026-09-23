<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Maintenance\Histograms;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Histograms::class)]
#[Medium]
final class HistogramsTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindResolvesHistogramColumnsAcrossGrammarReleases(string $version): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT, x INT)');
        $statement = (new Binder($schema))->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id,x WITH 10 BUCKETS');
        self::assertInstanceOf(UpdateHistogramStatement::class, $statement);
        self::assertSame(10, $statement->buckets?->value);
        self::assertCount(2, $statement->columns);
        self::assertInstanceOf(ColumnReference::class, $statement->columns[1]);
        self::assertSame('x', $statement->columns[1]->binding->column->name);
        self::assertSame($statement->table->id, $statement->columns[1]->binding->relationId);
    }

    #[TestWith(['0'])]
    #[TestWith(['1025'])]
    #[TestWith(['2147483647'])]
    public function testBucketsDiagnoseInvalidStructuralLimits(string $count): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('A histogram bucket limit must be an integer from 1 to 1024.');
        $binder->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id WITH ' . $count . ' BUCKETS');
    }

    public function testColumnsRetainUnknownColumnNamesWithDiagnostics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)')))->bind('ANALYZE TABLE t DROP HISTOGRAM ON missing', strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\DropHistogramStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference::class, $statement->columns[0]);
        self::assertSame(['missing'], $statement->columns[0]->name);
        self::assertSame('unknown-column', $statement->diagnostics[0]->reason);
    }

    #[TestWith(['ANALYZE TABLE t,u UPDATE HISTOGRAM ON id', 'A histogram request requires exactly one target table.'])]
    #[TestWith(["ANALYZE TABLE t UPDATE HISTOGRAM ON id,x USING DATA '{}'", 'Imported histogram data describes exactly one column.'])]
    public function testBindDiagnosesInvalidHistogramTargets(string $sql, string $message): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, x INT)', 'CREATE TABLE u(id INT)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage($message);
        $binder->bind($sql);
    }
}
