<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Sampling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Sampling\SamplingMethod;
use SqlSemantics\Model\Query\Sampling\TableSample;
use SqlSemantics\Model\Relation\NamedTableReference;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableSample::class)]
#[Medium]
final class TableSampleTest extends TestCase
{
    public function testPostgreSqlSampleKeepsMethodArgumentsAndSeed(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('SELECT a FROM ONLY t AS x TABLESAMPLE BERNOULLI (10) REPEATABLE (1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(NamedTableReference::class, $statement->from);
        $sample = $statement->from->sample;
        self::assertNotNull($sample);
        self::assertSame(SamplingMethod::Bernoulli, $sample->method);
        self::assertSame('10', $sample->arguments[0]->structure()->toString());
        self::assertSame('1', $sample->repeatable?->structure()->toString());
        $expected = 'SELECT "a" AS "a" FROM ONLY "public"."t" AS "x" TABLESAMPLE BERNOULLI(10) REPEATABLE(1)';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testExtensionMethodIsAQualifiedName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)'));
        $statement = $binder->bind('SELECT a FROM t TABLESAMPLE tsm_system_rows (10, 2)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(NamedTableReference::class, $statement->from);
        self::assertInstanceOf(QualifiedName::class, $statement->from->sample?->method);
        self::assertSame(['tsm_system_rows'], $statement->from->sample->method->parts);
        self::assertCount(2, $statement->from->sample->arguments);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testMySqlSampleKeepsItsPercentage(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind('SELECT a FROM t AS x TABLESAMPLE SYSTEM (?)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(NamedTableReference::class, $statement->from);
        self::assertSame(SamplingMethod::System, $statement->from->sample?->method);
        self::assertSame('SELECT `a` AS `a` FROM `t` AS `x` TABLESAMPLE SYSTEM(?)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBuiltInMethodWithTwoArgumentsIsInvalidSql(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SampleArguments->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t TABLESAMPLE SYSTEM (1, 2)');
    }

    public function testRejectsAMissingArgument(): void
    {
        $this->expectException(InvalidStructure::class);
        new TableSample(new QualifiedName(['m']), []);
    }

    public function testRejectsTwoPercentagesForABuiltInMethod(): void
    {
        $sample = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER)')))->bind('SELECT a FROM t TABLESAMPLE m (1, 2)');
        self::assertInstanceOf(BoundSelect::class, $sample);
        self::assertInstanceOf(NamedTableReference::class, $sample->from);
        $this->expectException(InvalidStructure::class);
        new TableSample(SamplingMethod::System, $sample->from->sample->arguments ?? []);
    }
}
