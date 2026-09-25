<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Statement\Loading\DuplicateRows;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Statement\Loading\LoadFormat;
use SqlSemantics\Model\Statement\Loading\LoadLayout;
use SqlSemantics\Model\Statement\Loading\LoadScheduling;
use SqlSemantics\Model\Statement\Loading\LoadSource;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LoadFileStatement::class)]
#[Medium]
final class LoadFileStatementTest extends TestCase
{
    public function testWithOriginRetainsTheLoad(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)')))->bind("LOAD DATA LOCAL INFILE 'f' INTO TABLE t (a, b) SET b = a");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA LOCAL INFILE 'f' INTO TABLE `t`(`a`, `b`) SET `b` = `a`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithFormatReadsXml(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t LINES TERMINATED BY '<r>'");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD XML INFILE 'f' INTO TABLE `t` ROWS IDENTIFIED BY '<r>'", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFormat(LoadFormat::Xml)));
        self::assertSame(LoadFormat::Data, $statement->format);
    }

    public function testWithFileReadsAnotherFile(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        $other = $binder->bind("LOAD DATA INFILE 'g' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertInstanceOf(LoadFileStatement::class, $other);
        self::assertSame("LOAD DATA INFILE 'g' INTO TABLE `t`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFile($other->file)));
    }

    public function testWithTableLoadsAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        $other = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE u");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertInstanceOf(LoadFileStatement::class, $other);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `u`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTable($other->table)));
    }

    public function testWithSchedulingRequestsLowPriority(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA LOW_PRIORITY INFILE 'f' INTO TABLE `t`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withScheduling(LoadScheduling::LowPriority)));
    }

    public function testWithLocalReadsAServerFile(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA LOCAL INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertFalse($statement->withLocal(false)->local);
        self::assertTrue($statement->local);
    }

    public function testWithLocationRejectsAUrlWithoutBulk(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA S3 'f' INTO TABLE `t`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withLocation(LoadSource::S3)));
        $this->expectException(InvalidStructure::class);
        $statement->withLocation(LoadSource::Url);
    }

    public function testWithDuplicatesIgnoresDuplicateRows(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' IGNORE INTO TABLE `t`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDuplicates(DuplicateRows::Ignore)));
    }

    public function testWithPartitionsRestrictsTheLoad(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t PARTITION (p0)");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame(['p0'], $statement->partitions?->names);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` PARTITION(`p1`, `p2`)", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withPartitions(new NamedPartitions(['p1', 'p2']))));
    }

    public function testWithLayoutReplacesTheInputLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t IGNORE 1 ROWS");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` CHARACTER SET `latin1`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withLayout(new LoadLayout('latin1'))));
        self::assertSame(1, $statement->layout->skippedRows);
    }

    public function testWithTargetsUsesEveryColumnWhenEmpty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t (a)");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withTargets([])));
        self::assertCount(1, $statement->targets);
    }

    public function testWithAssignmentsRemovesTheSetItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t SET a = DEFAULT");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t`", (new \SqlSemantics\SimpleSerializer())->serialize($statement->withAssignments([])));
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` SET `a` = DEFAULT", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new LoadFileStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), LoadFormat::Data, $statement->file, $statement->table);
    }
}
