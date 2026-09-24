<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Plan\ExplainBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Statement\Plan\ExplainConnectionStatement;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExplainBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExplainBinderTest extends TestCase
{
    #[TestWith(['DESC ANALYZE FOR CONNECTION 0'])]
    #[TestWith(['EXPLAIN ANALYZE FORMAT=TREE FOR CONNECTION 1'])]
    public function testBindDiagnosesAnalysisOfAnAlreadyRunningConnection(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('incompatible execution requirements');
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql, strict: false);
    }

    public function testMysqlRetainsAnalysisWithTheImplicitFormat(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('EXPLAIN ANALYZE SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertInstanceOf(MySqlPlan::class, $statement->options);
        self::assertTrue($statement->options->analyze);
        self::assertSame(MySqlFormat::Default, $statement->options->format);
        self::assertSame('EXPLAIN ANALYZE SELECT 1', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindRetainsTheConnectionNumberAndFormat(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('DESC FORMAT=JSON FOR CONNECTION 123');
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        self::assertSame('123', $statement->connection->spelling);
        self::assertSame(MySqlFormat::Json, $statement->format);
        self::assertSame('EXPLAIN FORMAT = JSON FOR CONNECTION 123', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    public function testBindUsesTheSelectedGrammarForLegacyExplainableCommands(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        $statement = $binder->bind('DESC FOR CONNECTION 1');
        self::assertInstanceOf(ExplainConnectionStatement::class, $statement);
        self::assertSame('1', $statement->connection->spelling);
        self::assertSame('EXPLAIN FOR CONNECTION 1', $statement->toString());
        $query = $binder->bind('EXPLAIN SELECT 1');
        self::assertInstanceOf(ExplainStatement::class, $query);
        self::assertSame('EXPLAIN SELECT 1', $query->toString());
    }

    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'explain format=json select 1', 'EXPLAIN FORMAT = JSON SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'explain format=`json` into @Var select 1', 'EXPLAIN FORMAT = JSON INTO @`Var` SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'explain analyze format=tree select 1', 'EXPLAIN ANALYZE FORMAT = TREE SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-8.4.7', 'desc t', 'DESCRIBE `t`'])]
    #[TestWith([Dialect::MySql, 'mysql-5.6.51', 'explain extended select 1', 'EXPLAIN EXTENDED SELECT 1'])]
    #[TestWith([Dialect::MySql, 'mysql-5.6.51', 'explain partitions select 1', 'EXPLAIN PARTITIONS SELECT 1'])]
    #[TestWith([Dialect::Sqlite, null, 'explain query plan select id from t', 'EXPLAIN QUERY PLAN SELECT "id" AS "id" FROM "main"."t"'])]
    #[TestWith([Dialect::Sqlite, null, 'explain select 1', 'EXPLAIN SELECT 1'])]
    #[TestWith([Dialect::PostgreSql, null, 'explain (analyze, format json) select 1', 'EXPLAIN(ANALYZE TRUE, VERBOSE FALSE, COSTS TRUE, SETTINGS FALSE, GENERIC_PLAN FALSE, BUFFERS FALSE, WAL FALSE, MEMORY FALSE, SERIALIZE NONE, FORMAT JSON) SELECT 1'])]
    public function testBindWrapsTheExplainedCommandOfEachDialect(Dialect $dialect, ?string $version, string $sql, string $expected): void
    {
        $schema = (new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INT)');
        self::assertSame($expected, (new Binder($schema))->bind($sql)->toString());
    }

    public function testBindRejectsAnUnknownFormat(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ExplainSetting->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('explain format=xml select 1');
    }

    public function testMysqlReadsEachPlanSetting(): void
    {
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('explain analyze format=tree select 1')->find('explain_stmt')[0];
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::MySql), ''));
        $plan = ExplainBinder::mysql($source, $context);
        self::assertSame(MySqlFormat::Tree, $plan->format);
        self::assertTrue($plan->analyze);
        self::assertFalse($plan->extended);
        self::assertNull($plan->variable);
    }

    public function testMysqlDiagnosesAnIncompatibleCombination(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ExplainCombination->message());
        (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('explain format=tree into @v select 1');
    }
}
