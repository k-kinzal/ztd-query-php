<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Subscription as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\SubscriptionParameter::class)]
#[Medium]
final class SubscriptionParameterTest extends TestCase
{
    public function testEveryCreationOptionIsNamedInDeclarationOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE SUBSCRIPTION s CONNECTION 'c' PUBLICATION p WITH (origin = any, failover, run_as_owner, password_required, disable_on_error, two_phase, streaming, binary, synchronous_commit = off, copy_data, slot_name = x, create_slot, enabled, connect)");
        self::assertInstanceOf(Statement\CreateSubscriptionStatement::class, $statement);
        self::assertSame(array_values(array_filter(Operand\SubscriptionParameter::cases(), static fn (Operand\SubscriptionParameter $parameter): bool => $parameter !== Operand\SubscriptionParameter::Refresh)), $statement->options->parameters());
    }
}
