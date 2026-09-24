<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Loading\BulkLoadStatement;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\Loading\LineLayout;
use SqlSemantics\Model\Statement\Loading\LoadLayout;
use SqlSemantics\Model\Statement\Loading\LoadSource;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BulkLoadStatement::class)]
#[Medium]
final class BulkLoadStatementTest extends TestCase
{
    public function testWithOriginRetainsTheLoad(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` ALGORITHM = BULK", $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithLocationReadsAUrl(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame("LOAD DATA FROM URL 'f' INTO TABLE `t` ALGORITHM = BULK", $statement->withLocation(LoadSource::Url)->toString());
    }

    public function testWithFileReadsAnotherPrefix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $file = Expression::literal('g.', Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $file);
        self::assertSame("LOAD DATA INFILE 'g.' INTO TABLE `t` ALGORITHM = BULK", $statement->withFile($file)->toString());
    }

    public function testWithTableLoadsAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $other = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE u ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(BulkLoadStatement::class, $other);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `u` ALGORITHM = BULK", $statement->withTable($other->table)->toString());
    }

    public function testWithFileCountRequiresAPositiveCount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' COUNT 2 INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertNull($statement->withFileCount(null)->fileCount);
        $this->expectException(InvalidStructure::class);
        $statement->withFileCount(0);
    }

    public function testWithKeyOrderedDeclaresTheInputSorted(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' IN PRIMARY KEY ORDER INTO TABLE `t` ALGORITHM = BULK", $statement->withKeyOrdered(true)->toString());
    }

    public function testWithDuplicatesAcceptsOnlyReplace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame(DuplicateRows::Replace, $statement->withDuplicates(DuplicateRows::Replace)->duplicates);
        $this->expectException(InvalidStructure::class);
        $statement->withDuplicates(DuplicateRows::Ignore);
    }

    public function testWithPartitionsRestrictsTheLoad(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame(['p'], $statement->withPartitions(new NamedPartitions(['p']))->partitions?->names);
    }

    public function testWithLayoutRejectsALinePrefix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $prefix = Expression::literal('>', Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $prefix);
        self::assertSame(2, $statement->withLayout(new LoadLayout(skippedRows: 2))->layout->skippedRows);
        $this->expectException(InvalidStructure::class);
        $statement->withLayout(new LoadLayout(lines: new LineLayout(null, $prefix)));
    }

    public function testWithCompressionNamesTheAlgorithm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $zstd = Expression::literal('zstd', Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $zstd);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` COMPRESSION = 'zstd' ALGORITHM = BULK", $statement->withCompression($zstd)->toString());
    }

    public function testWithParallelRequiresMySql82(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withParallel(4);
    }

    public function testWithMemoryRequiresDecimalBytes(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t MEMORY = 2K ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame('2048', $statement->memory);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` MEMORY = 10 ALGORITHM = BULK", $statement->withMemory('10')->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withMemory('010');
    }
}
