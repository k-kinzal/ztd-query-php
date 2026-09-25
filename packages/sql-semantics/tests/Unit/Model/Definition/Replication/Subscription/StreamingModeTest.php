<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\StreamingMode::class)]
#[Medium]
final class StreamingModeTest extends TestCase
{
    #[TestWith([Operand\StreamingMode::Off, 'off'])]
    #[TestWith([Operand\StreamingMode::On, 'TRUE'])]
    #[TestWith([Operand\StreamingMode::On, '1'])]
    #[TestWith([Operand\StreamingMode::Parallel, "'Parallel'"])]
    public function testEachValueSurvivesBindingAndSerialization(Operand\StreamingMode $value, string $spelling): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()));
        $statement = $binder->bind('ALTER SUBSCRIPTION s SET (streaming = ' . $spelling . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($value, $statement->options->streaming);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
