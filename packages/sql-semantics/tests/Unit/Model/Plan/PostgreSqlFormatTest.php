<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\PostgreSqlFormat;
use SqlSemantics\Model\Plan\PostgreSqlPlan;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlFormat::class)]
#[Medium]
final class PostgreSqlFormatTest extends TestCase
{
    public function testRepresentsEveryPostgreSqlPlanFormat(): void
    {
        self::assertSame(['TEXT', 'JSON', 'XML', 'YAML'], array_column(PostgreSqlFormat::cases(), 'value'));
    }

    #[TestWith(['EXPLAIN SELECT 1', PostgreSqlFormat::Text])]
    #[TestWith(['EXPLAIN (FORMAT JSON) SELECT 1', PostgreSqlFormat::Json])]
    #[TestWith(['EXPLAIN (FORMAT XML) SELECT 1', PostgreSqlFormat::Xml])]
    #[TestWith(['EXPLAIN (FORMAT YAML) SELECT 1', PostgreSqlFormat::Yaml])]
    public function testClassifiesTheRequestedFormat(string $sql, PostgreSqlFormat $format): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlPlan::class, $statement->options);
        self::assertSame($format, $statement->options->format);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
