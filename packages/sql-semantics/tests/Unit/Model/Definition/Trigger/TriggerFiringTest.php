<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Trigger\TriggerFiring;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Trigger\AlterEventTriggerFiringStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TriggerFiring::class)]
#[Medium]
final class TriggerFiringTest extends TestCase
{
    #[TestWith([TriggerFiring::Origin])]
    #[TestWith([TriggerFiring::Replica])]
    #[TestWith([TriggerFiring::Always])]
    #[TestWith([TriggerFiring::Disabled])]
    public function testTheFiringPolicySurvivesBindingAndSerialization(TriggerFiring $firing): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER EVENT TRIGGER audit ' . $firing->value);
        self::assertInstanceOf(AlterEventTriggerFiringStatement::class, $statement);
        self::assertSame($firing, $statement->firing);
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(AlterEventTriggerFiringStatement::class, $rebound);
        self::assertSame($firing, $rebound->firing);
    }

}
