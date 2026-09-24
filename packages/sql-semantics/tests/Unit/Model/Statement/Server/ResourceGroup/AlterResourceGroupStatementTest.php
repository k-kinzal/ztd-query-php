<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Server\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Configuration\ResourceGroup\ResourceGroupState;
use SqlSemantics\Model\Statement\Server\ResourceGroup\AlterResourceGroupStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterResourceGroupStatement::class)]
#[Medium]
final class AlterResourceGroupStatementTest extends TestCase
{
    public function testWithOriginRetainsTheChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP g VCPU 1 DISABLE FORCE');
        self::assertInstanceOf(AlterResourceGroupStatement::class, $statement);
        self::assertSame('ALTER RESOURCE GROUP `g` VCPU = 1 DISABLE FORCE', $statement->withOrigin($statement->origin)->toString());
    }

    public function testWithNameChangesAnotherGroup(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP g');
        self::assertInstanceOf(AlterResourceGroupStatement::class, $statement);
        self::assertSame('ALTER RESOURCE GROUP `h`', $statement->withName('h')->toString());
    }

    public function testWithCpusReplacesTheRanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP g');
        self::assertInstanceOf(AlterResourceGroupStatement::class, $statement);
        self::assertSame('ALTER RESOURCE GROUP `g` VCPU = 0-7', $statement->withCpus([new CpuRange(0, 7)])->toString());
        self::assertSame([], $statement->cpus);
    }

    public function testWithPriorityAcceptsEitherTypeRange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP g');
        self::assertInstanceOf(AlterResourceGroupStatement::class, $statement);
        self::assertSame([-20, 19], [$statement->withPriority(-20)->priority, $statement->withPriority(19)->priority]);
        $this->expectException(InvalidStructure::class);
        $statement->withPriority(20);
    }

    public function testWithStateEnablesTheGroup(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP g');
        self::assertInstanceOf(AlterResourceGroupStatement::class, $statement);
        self::assertSame('ALTER RESOURCE GROUP `g` ENABLE', $statement->withState(ResourceGroupState::Enabled)->toString());
        self::assertNull($statement->state);
    }

    public function testWithForceRequestsForce(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER RESOURCE GROUP g DISABLE');
        self::assertInstanceOf(AlterResourceGroupStatement::class, $statement);
        self::assertTrue($statement->withForce(true)->force);
        self::assertFalse($statement->force);
    }
}
