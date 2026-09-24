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

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testBindImportsHistogramDataForOneColumn(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind("analyze table t update histogram on n using data 'x'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\ImportHistogramStatement::class, $statement);
        self::assertSame('t', $statement->table->declaration->name);
        self::assertInstanceOf(ColumnReference::class, $statement->column);
        self::assertSame('n', $statement->column->binding->column->name);
        self::assertSame("'x'", $statement->data->text);
        self::assertSame("ANALYZE TABLE `t` UPDATE HISTOGRAM ON `n` USING DATA 'x'", $statement->toString());
    }

    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testBindDropsHistogramsWrittenInLowercase(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('analyze table t drop histogram on id');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\DropHistogramStatement::class, $statement);
        self::assertSame('ANALYZE TABLE `t` DROP HISTOGRAM ON `id`', $statement->toString());
    }

    #[TestWith(['mysql-8.0.44', 'analyze table t update histogram on id with 0010 buckets', 10, \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy::Default])]
    #[TestWith(['mysql-8.0.44', 'ANALYZE TABLE t UPDATE HISTOGRAM ON id', null, \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy::Default])]
    #[TestWith(['mysql-8.4.7', 'analyze table t update histogram on id with 1 buckets manual update', 1, \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy::Manual])]
    #[TestWith(['mysql-8.4.7', 'ANALYZE TABLE t UPDATE HISTOGRAM ON id WITH 00001024 BUCKETS AUTO UPDATE', 1024, \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy::Automatic])]
    #[TestWith(['mysql-8.4.7', 'ANALYZE TABLE t UPDATE HISTOGRAM ON id AUTO UPDATE', null, \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy::Automatic])]
    public function testBindReadsBucketsAndRefreshPolicy(string $version, string $sql, ?int $buckets, \SqlSemantics\Model\Maintenance\Histogram\RefreshPolicy $refresh): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(UpdateHistogramStatement::class, $statement);
        self::assertSame($buckets, $statement->buckets?->value);
        self::assertSame($refresh, $statement->refresh);
    }

    public function testColumnsBindsEachNamedColumn(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)');
        $statement = (new Binder($schema))->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id');
        self::assertInstanceOf(UpdateHistogramStatement::class, $statement);
        $names = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('ANALYZE TABLE t UPDATE HISTOGRAM ON n, id'), ['ident_string_list'])[0];
        $columns = Histograms::columns($names, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql), [$statement->table]));
        self::assertCount(2, $columns);
        self::assertInstanceOf(ColumnReference::class, $columns[0]);
        self::assertSame('n', $columns[0]->binding->column->name);
    }

    public function testBucketsReadsTheLimitWithoutLeadingZeros(): void
    {
        $node = new \SqlParser\Parser\Node('opt_histogram_num_buckets', 0, [new \SqlParser\Lexer\Token(1, 'WITH', 'WITH', 0), new \SqlParser\Lexer\Token(2, 'NUM', '0010', 5), new \SqlParser\Lexer\Token(3, 'BUCKETS_SYM', 'BUCKETS', 10)]);
        self::assertSame(10, Histograms::buckets($node)->value);
    }
}
