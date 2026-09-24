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

#[CoversClass(Operand\NoSlot::class)]
#[Medium]
final class NoSlotTest extends TestCase
{
    #[TestWith([Operand\NoSlot::None, 'NONE'])]
    #[TestWith([Operand\NoSlot::None, "'none'"])]
    public function testEachValueSurvivesBindingAndSerialization(Operand\NoSlot $value, string $spelling): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()));
        $statement = $binder->bind('ALTER SUBSCRIPTION s SET (slot_name = ' . $spelling . ')');
        self::assertInstanceOf(Statement\AlterSubscriptionOptionsStatement::class, $statement);
        self::assertSame($value, $statement->options->slotName);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
