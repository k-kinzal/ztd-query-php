<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading\BulkLoadStatement;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Statement\Loading\LoadFormat;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Loads;

#[CoversClass(Loads::class)]
#[Medium]
final class LoadsTest extends TestCase
{
    public function testWriteNormalizesSynonymsAndDropsIgnoredOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA FROM INFILE 'f' IN PRIMARY KEY ORDER INTO TABLE t CHARSET utf8 COLUMNS TERMINATED BY ',' IGNORE 2 ROWS PARALLEL = 2 MEMORY = 1M");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t` CHARACTER SET `utf8` FIELDS TERMINATED BY ',' IGNORE 2 LINES", Loads::write($statement)->toString());
    }

    public function testLayoutSpellsAnXmlRowTerminatorAsTheRowTag(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD XML INFILE 'f' INTO TABLE t LINES TERMINATED BY '<r>'");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("ROWS IDENTIFIED BY '<r>'", (new Tree('layout', Loads::layout($statement, LoadFormat::Xml)))->toString());
        self::assertSame("LINES TERMINATED BY '<r>'", (new Tree('layout', Loads::layout($statement, LoadFormat::Data)))->toString());
    }

    public function testSeparatorsOmitsEmptyClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t FIELDS OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '!'");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame("FIELDS OPTIONALLY ENCLOSED BY '\"' ESCAPED BY '!'", (new Tree('separators', Loads::separators($statement->layout, null)))->toString());
    }

    public function testRowsWritesTargetsAndAssignments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t (a) SET a = 2");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame('(`a`) SET `a` = 2', (new Tree('rows', Loads::rows($statement)))->toString());
    }

    public function testBulkEndsWithTheAlgorithm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t PARALLEL = 3 MEMORY = 1K ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame('PARALLEL = 3 MEMORY = 1024 ALGORITHM = BULK', (new Tree('bulk', Loads::bulk($statement)))->toString());
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerWriteSpellsEachLoadForm(): array
    {
        return [
            [Dialect::MySql, null, 'LOAD DATA LOW_PRIORITY LOCAL INFILE \'f\' INTO TABLE t', 'LOAD DATA LOW_PRIORITY LOCAL INFILE \'f\' INTO TABLE `t`'],
            [Dialect::MySql, null, 'LOAD XML CONCURRENT INFILE \'f\' INTO TABLE t ROWS IDENTIFIED BY \'<r>\'', 'LOAD XML CONCURRENT INFILE \'f\' INTO TABLE `t` ROWS IDENTIFIED BY \'<r>\''],
            [Dialect::MySql, null, 'LOAD DATA FROM S3 \'f\' INTO TABLE t', 'LOAD DATA S3 \'f\' INTO TABLE `t`'],
            [Dialect::MySql, null, 'LOAD DATA INFILE \'f\' INTO TABLE t PARTITION (p0, p1)', 'LOAD DATA INFILE \'f\' INTO TABLE `t` PARTITION(`p0`, `p1`)'],
            [Dialect::MySql, null, 'LOAD DATA INFILE \'f\' INTO TABLE t LINES STARTING BY \'x\' TERMINATED BY \'y\'', 'LOAD DATA INFILE \'f\' INTO TABLE `t` LINES STARTING BY \'x\' TERMINATED BY \'y\''],
            [Dialect::MySql, null, 'LOAD DATA FROM URL \'f\' INTO TABLE t ALGORITHM = BULK', 'LOAD DATA FROM URL \'f\' INTO TABLE `t` ALGORITHM = BULK'],
            [Dialect::MySql, null, 'LOAD DATA INFILE \'f\' INTO TABLE t (a, @b) SET a = @b', 'LOAD DATA INFILE \'f\' INTO TABLE `t`(`a`, @`b`) SET `a` = @`b`'],
            [Dialect::MySql, null, 'LOAD DATA FROM S3 \'f\' COUNT 3 INTO TABLE t ALGORITHM = BULK', 'LOAD DATA FROM S3 \'f\' COUNT 3 INTO TABLE `t` ALGORITHM = BULK'],
            [Dialect::MySql, null, 'LOAD DATA FROM S3 \'f\' INTO TABLE t COMPRESSION = \'ZSTD\' ALGORITHM = BULK', 'LOAD DATA FROM S3 \'f\' INTO TABLE `t` COMPRESSION = \'ZSTD\' ALGORITHM = BULK'],
        ];
    }

    #[DataProvider('providerWriteSpellsEachLoadForm')]
    public function testWriteSpellsEachLoadForm(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertTrue($statement instanceof LoadFileStatement || $statement instanceof BulkLoadStatement);
        self::assertSame($expected, Loads::write($statement)->toString());
    }
}
