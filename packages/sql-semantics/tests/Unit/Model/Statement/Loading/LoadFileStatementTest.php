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
        self::assertSame("LOAD DATA LOCAL INFILE 'f' INTO TABLE `t`(`a`, `b`) SET `b` = `a`", $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithFormatReadsXml(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t LINES TERMINATED BY '<r>'");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD XML INFILE 'f' INTO TABLE `t` ROWS IDENTIFIED BY '<r>'", $statement->withFormat(LoadFormat::Xml)->toString());
        self::assertSame(LoadFormat::Data, $statement->format);
    }

    public function testWithFileReadsAnotherFile(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        $other = $binder->bind("LOAD DATA INFILE 'g' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertInstanceOf(LoadFileStatement::class, $other);
        self::assertSame("LOAD DATA INFILE 'g' INTO TABLE `t`", $statement->withFile($other->file)->toString());
    }

    public function testWithTableLoadsAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT); CREATE TABLE u(a INT)'));
        $statement = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        $other = $binder->bind("LOAD DATA INFILE 'f' INTO TABLE u");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertInstanceOf(LoadFileStatement::class, $other);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `u`", $statement->withTable($other->table)->toString());
    }

    public function testWithSchedulingRequestsLowPriority(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA LOW_PRIORITY INFILE 'f' INTO TABLE `t`", $statement->withScheduling(LoadScheduling::LowPriority)->toString());
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
        self::assertSame("LOAD DATA S3 'f' INTO TABLE `t`", $statement->withLocation(LoadSource::S3)->toString());
        $this->expectException(InvalidStructure::class);
        $statement->withLocation(LoadSource::Url);
    }

    public function testWithDuplicatesIgnoresDuplicateRows(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' IGNORE INTO TABLE `t`", $statement->withDuplicates(DuplicateRows::Ignore)->toString());
    }

    public function testWithPartitionsRestrictsTheLoad(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t PARTITION (p0)");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame(['p0'], $statement->partitions?->names);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` PARTITION(`p1`, `p2`)", $statement->withPartitions(new NamedPartitions(['p1', 'p2']))->toString());
    }

    public function testWithLayoutReplacesTheInputLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t IGNORE 1 ROWS");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` CHARACTER SET `latin1`", $statement->withLayout(new LoadLayout('latin1'))->toString());
        self::assertSame(1, $statement->layout->skippedRows);
    }

    public function testWithTargetsUsesEveryColumnWhenEmpty(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t (a)");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t`", $statement->withTargets([])->toString());
        self::assertCount(1, $statement->targets);
    }

    public function testWithAssignmentsRemovesTheSetItems(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t SET a = DEFAULT");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t`", $statement->withAssignments([])->toString());
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` SET `a` = DEFAULT", $statement->toString());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new LoadFileStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), LoadFormat::Data, $statement->file, $statement->table);
    }
}
