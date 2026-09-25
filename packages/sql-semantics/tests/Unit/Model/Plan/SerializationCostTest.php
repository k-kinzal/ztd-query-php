<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Plan\PostgreSqlPlan;
use SqlSemantics\Model\Plan\SerializationCost;
use SqlSemantics\Model\Statement\Plan\ExplainStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SerializationCost::class)]
#[Medium]
final class SerializationCostTest extends TestCase
{
    public function testRepresentsEverySerializationChoice(): void
    {
        self::assertSame(['NONE', 'TEXT', 'BINARY'], array_column(SerializationCost::cases(), 'value'));
    }

    #[TestWith(['EXPLAIN (ANALYZE) SELECT 1', SerializationCost::None])]
    #[TestWith(['EXPLAIN (ANALYZE, SERIALIZE) SELECT 1', SerializationCost::Text])]
    #[TestWith(['EXPLAIN (ANALYZE, SERIALIZE TEXT) SELECT 1', SerializationCost::Text])]
    #[TestWith(['EXPLAIN (ANALYZE, SERIALIZE BINARY) SELECT 1', SerializationCost::Binary])]
    public function testClassifiesTheRequestedSerialization(string $sql, SerializationCost $serialization): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ExplainStatement::class, $statement);
        self::assertInstanceOf(PostgreSqlPlan::class, $statement->options);
        self::assertSame($serialization, $statement->options->serialization);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
