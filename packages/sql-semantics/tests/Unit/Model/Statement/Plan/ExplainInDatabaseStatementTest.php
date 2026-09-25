<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Plan\ExplainInDatabaseStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExplainInDatabaseStatement::class)]
#[Medium]
final class ExplainInDatabaseStatementTest extends TestCase
{
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testResolvesTheExplainedStatementInTheNamedDatabase(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, 'sales', $version))->build('CREATE TABLE orders(id INT)'));
        $statement = $binder->bind('EXPLAIN FOR DATABASE sales UPDATE orders SET id = 1');
        self::assertInstanceOf(ExplainInDatabaseStatement::class, $statement);
        self::assertSame('sales', $statement->database);
        self::assertSame([], $statement->diagnostics);
        self::assertSame('EXPLAIN FOR DATABASE `sales` UPDATE `sales`.`orders` SET `id` = 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWithDatabaseReplacesTheScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('EXPLAIN FOR DATABASE a SELECT 1');
        self::assertInstanceOf(ExplainInDatabaseStatement::class, $statement);
        self::assertSame('EXPLAIN FOR DATABASE `b` SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDatabase('b')));
        self::assertSame('a', $statement->database);
    }

    public function testWithOptionsReplacesThePlanOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('EXPLAIN FOR DATABASE a SELECT 1');
        self::assertInstanceOf(ExplainInDatabaseStatement::class, $statement);
        self::assertSame('EXPLAIN FORMAT = JSON FOR DATABASE `a` SELECT 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOptions(new MySqlPlan(MySqlFormat::Json))));
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('EXPLAIN FOR DATABASE a SELECT 1');
        self::assertInstanceOf(ExplainInDatabaseStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('other', $statement->source, Dialect::MySql));
        self::assertSame([$statement->database, $statement->statement, $statement->options], [$copy->database, $copy->statement, $copy->options]);
    }

    public function testRejectsAnUnexplainableStatement(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('BEGIN');
        $this->expectException(InvalidStructure::class);
        new ExplainInDatabaseStatement(new Origin('s', $statement->source, Dialect::MySql), 'a', $statement);
    }

    public function testRejectsAnEmptyDatabaseName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        $this->expectException(InvalidStructure::class);
        new ExplainInDatabaseStatement(new Origin('s', $statement->source, Dialect::MySql), '', $statement);
    }
}
