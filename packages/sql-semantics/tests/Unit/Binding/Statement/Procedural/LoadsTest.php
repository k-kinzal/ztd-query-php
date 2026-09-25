<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\Loads;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\VariableReference;
use SqlSemantics\Model\Statement\Loading\BulkLoadStatement;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\VariableDefinition;
use SqlSemantics\Schema\VariableScope;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(Loads::class)]
#[Medium]
final class LoadsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsARowLoadAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind("LOAD XML CONCURRENT LOCAL INFILE 'rows.xml' IGNORE INTO TABLE t PARTITION (p) CHARSET latin1 ROWS IDENTIFIED BY '<row>' IGNORE 1 LINES (a, b) SET b = a + 1");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame(['XML', 'CONCURRENT', true, 'IGNORE', ['p'], 'latin1', "'<row>'", 1], [$statement->format->value, $statement->scheduling?->value, $statement->local, $statement->duplicates?->value, $statement->partitions?->names, $statement->layout->characterSet, $statement->layout->lines->terminator?->text, $statement->layout->skippedRows]);
        self::assertInstanceOf(ColumnReference::class, $statement->targets[0]);
        self::assertSame("LOAD XML CONCURRENT LOCAL INFILE 'rows.xml' IGNORE INTO TABLE `t` PARTITION(`p`) CHARACTER SET `latin1` ROWS IDENTIFIED BY '<row>' IGNORE 1 LINES(`a`, `b`) SET `b` = (`a` + 1)", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(["LOAD DATA FROM URL 'u' INTO TABLE t"])]
    #[TestWith(["LOAD DATA INFILE 'f' COUNT 2 INTO TABLE t"])]
    #[TestWith(["LOAD DATA INFILE 'f' INTO TABLE t COMPRESSION = 'zstd'"])]
    #[TestWith(["LOAD DATA INFILE 'f' INTO TABLE t FIELDS ESCAPED BY 'ab'"])]
    #[TestWith(["LOAD XML INFILE 'f' INTO TABLE t ALGORITHM = BULK"])]
    #[TestWith(["LOAD DATA LOCAL INFILE 'f' INTO TABLE t ALGORITHM = BULK"])]
    #[TestWith(["LOAD DATA INFILE 'f' IGNORE INTO TABLE t ALGORITHM = BULK"])]
    #[TestWith(["LOAD DATA INFILE 'f' INTO TABLE t LINES STARTING BY 'x' ALGORITHM = BULK"])]
    public function testBindDiagnosesClausesTheServerRejects(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::LoadOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind($sql);
    }

    public function testBulkReadsTheBulkLoaderOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA FROM S3 's3://b/t.' COUNT 3 IN PRIMARY KEY ORDER REPLACE INTO TABLE t COMPRESSION = 'zstd' FIELDS TERMINATED BY ',' PARALLEL = 2 MEMORY = 0x10 ALGORITHM = BULK");
        self::assertInstanceOf(BulkLoadStatement::class, $statement);
        self::assertSame(['S3', 3, true, 'REPLACE', "'zstd'", 2, '16'], [$statement->location->value, $statement->fileCount, $statement->keyOrdered, $statement->duplicates?->value, $statement->compression?->text, $statement->parallel, $statement->memory]);
    }

    public function testBulkRejectsAColumnList(): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t (a) ALGORITHM = BULK");
    }

    public function testWordReturnsAnEmptyStringForAnAbsentClause(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse("LOAD DATA low_priority INFILE 'f' INTO TABLE t")->find('load_stmt')[0];
        self::assertSame(['LOW_PRIORITY', ''], [Loads::word($node, 'load_data_lock'), Loads::word($node, 'opt_local')]);
    }

    public function testTargetsBindsColumnsAndUserVariables(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')->withVariables(new VariableDefinition('x', VariableScope::User, TypeDescriptor::builtin(Dialect::MySql, 'integer')));
        $statement = (new Binder($schema))->bind("LOAD DATA INFILE 'f' INTO TABLE t (@x, a)");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertInstanceOf(VariableReference::class, $statement->targets[0]);
        self::assertInstanceOf(ColumnReference::class, $statement->targets[1]);
        self::assertSame("LOAD DATA INFILE 'f' INTO TABLE `t`(@`x`, `a`)", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testAssignmentsBindsEachSetItem(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)')))->bind("LOAD DATA INFILE 'f' INTO TABLE t SET a = 1, b = DEFAULT");
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertCount(2, $statement->assignments);
    }

    #[TestWith(["load xml infile '/f' into table t rows identified by '<r>'", "LOAD XML INFILE '/f' INTO TABLE `t` ROWS IDENTIFIED BY '<r>'"])]
    #[TestWith(["load data infile '/f' into table t (a, @v) set b = @v", "LOAD DATA INFILE '/f' INTO TABLE `t`(`a`, @`v`) SET `b` = @`v`"])]
    #[TestWith(["load data local infile '/f' into table t", "LOAD DATA LOCAL INFILE '/f' INTO TABLE `t`"])]
    public function testBindReadsLowerCaseLoads(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql, strict: false);
        self::assertInstanceOf(LoadFileStatement::class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTargetsAndAssignmentsReadNothingWithoutLists(): void
    {
        $node = \SqlSemantics\Ast\Tree::outer((new DialectParser(Dialect::MySql))->parse("LOAD DATA INFILE '/f' INTO TABLE t"), ['load_stmt'])[0];
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertSame([], Loads::targets($node, $scope));
        self::assertSame([], Loads::assignments($node, $scope));
    }
}
