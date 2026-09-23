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

}
