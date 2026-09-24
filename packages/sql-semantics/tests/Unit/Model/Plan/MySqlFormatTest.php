<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\MySqlFormat;
use SqlSemantics\Model\Plan\MySqlPlan;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MySqlFormat::class)]
#[Medium]
final class MySqlFormatTest extends TestCase
{
    public function testRepresentsEveryMySqlPlanFormat(): void
    {
        self::assertSame(['', 'TRADITIONAL', 'JSON', 'TREE'], array_column(MySqlFormat::cases(), 'value'));
    }

    #[TestWith(['EXPLAIN SELECT 1', MySqlFormat::Default, 'EXPLAIN SELECT 1'])]
    #[TestWith(['EXPLAIN FORMAT=JSON SELECT 1', MySqlFormat::Json, 'EXPLAIN FORMAT = JSON SELECT 1'])]
    #[TestWith(['EXPLAIN FORMAT=TREE SELECT 1', MySqlFormat::Tree, 'EXPLAIN FORMAT = TREE SELECT 1'])]
    #[TestWith(['EXPLAIN FORMAT=TRADITIONAL SELECT 1', MySqlFormat::Traditional, 'EXPLAIN FORMAT = TRADITIONAL SELECT 1'])]
    public function testClassifiesTheRequestedFormat(string $sql, MySqlFormat $format, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertInstanceOf(MySqlPlan::class, $statement->options);
        self::assertSame($format, $statement->options->format);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
