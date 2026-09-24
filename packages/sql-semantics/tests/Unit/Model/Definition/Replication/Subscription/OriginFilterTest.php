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

#[CoversClass(Operand\OriginFilter::class)]
#[Medium]
final class OriginFilterTest extends TestCase
{
    #[TestWith([Operand\OriginFilter::None, 'none'])]
    #[TestWith([Operand\OriginFilter::Any, "'ANY'"])]
    public function testEachValueSurvivesBindingAndSerialization(Operand\OriginFilter $value, string $spelling): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()));
        $statement = $binder->bind('ALTER SUBSCRIPTION s SET (origin = ' . $spelling . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($value, $statement->options->origin);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
