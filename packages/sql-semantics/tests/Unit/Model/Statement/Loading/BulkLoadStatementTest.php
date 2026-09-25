<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
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
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithLocationReadsAUrl(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame("LOAD DATA FROM URL 'f' INTO TABLE `t` ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withLocation(LoadSource::Url)));
    }

    public function testWithFileReadsAnotherPrefix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $file = Expression::literal('g.', Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $file);
        self::assertSame("LOAD DATA INFILE 'g.' INTO TABLE `t` ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFile($file)));
    }

    public function testWithTableLoadsAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $other = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE u ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(BulkLoadStatement::class, $other);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `u` ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTable($other->table)));
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
        self::assertSame("LOAD DATA INFILE 'f' IN PRIMARY KEY ORDER INTO TABLE `t` ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withKeyOrdered(true)));
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
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` COMPRESSION = 'zstd' ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withCompression($zstd)));
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
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` MEMORY = 10 ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withMemory('10')));
        $this->expectException(InvalidStructure::class);
        $statement->withMemory('010');
    }

    public function testKeyOrderedIsOffByDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t FIELDS TERMINATED BY ',' ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        $copy = new BulkLoadStatement($statement->origin, $statement->location, $statement->file, $statement->table);
        self::assertFalse($copy->keyOrdered);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` FIELDS TERMINATED BY ',' ALGORITHM = BULK", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testOriginRequiresMySql80(): void
    {
        $bulk = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(BulkLoadStatement::class, $bulk);
        $this->expectException(InvalidStructure::class);
        new BulkLoadStatement($legacy->origin, $bulk->location, $bulk->file, $bulk->table);
    }

    public function testWithFileRequiresATextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $number = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        $statement->withFile($number);
    }

    #[TestWith(['mysql-8.2.0', 2, null, "LOAD DATA INFILE 'f' INTO TABLE `t` PARALLEL = 2 ALGORITHM = BULK"])]
    #[TestWith(['mysql-8.2.0', 0, '0', "LOAD DATA INFILE 'f' INTO TABLE `t` PARALLEL = 0 MEMORY = 0 ALGORITHM = BULK"])]
    public function testWithParallelAndMemoryAcceptTheirRelease(string $version, int $parallel, ?string $memory, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement->withParallel($parallel)->withMemory($memory)));
    }

    #[TestWith(['mysql-8.1.0', 2, null])]
    #[TestWith(['mysql-8.2.0', -1, null])]
    #[TestWith(['mysql-8.2.0', null, '01'])]
    #[TestWith(['mysql-8.2.0', null, "1\n"])]
    public function testWithParallelAndMemoryRejectInvalidRequests(string $version, ?int $parallel, ?string $memory): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withParallel($parallel)->withMemory($memory);
    }

    public function testWithFileCountAcceptsOneFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame(1, $statement->withFileCount(1)->fileCount);
    }

    public function testWithCompressionRequiresMySql84AndATextLiteral(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.3.0'))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $name = Expression::literal('zstd', Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $name);
        $this->expectException(InvalidStructure::class);
        $statement->withCompression($name);
    }

    public function testWithCompressionRejectsANumber(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t ALGORITHM = BULK");
        $number = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertInstanceOf(Literal::class, $number);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A compression algorithm is named by a text literal.');
        $statement->withCompression($number);
    }
}
