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
use SqlSemantics\Model\Configuration\ResourceGroup\ThreadCategory;
use SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateResourceGroupStatement::class)]
#[Medium]
final class CreateResourceGroupStatementTest extends TestCase
{
    public function testWithOriginRetainsTheDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP g TYPE = SYSTEM VCPU = 0-1 THREAD_PRIORITY = -5');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('CREATE RESOURCE GROUP `g` TYPE = SYSTEM VCPU = 0-1 THREAD_PRIORITY = -5 ENABLE', $copy->toString());
    }

    public function testWithNameCreatesAnotherGroup(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP g TYPE = USER');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $statement);
        self::assertSame('CREATE RESOURCE GROUP `h` TYPE = USER ENABLE', $statement->withName('h')->toString());
        self::assertSame('g', $statement->name);
    }

    public function testWithTypeRequiresAPrioritySuitingTheType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP g TYPE = USER THREAD_PRIORITY = 0');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $statement);
        self::assertSame(ThreadCategory::System, $statement->withType(ThreadCategory::System)->type);
        $this->expectException(InvalidStructure::class);
        $statement->withPriority(-1);
    }

    public function testWithCpusReplacesTheRanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP g TYPE = USER VCPU = 1');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $statement);
        self::assertSame('CREATE RESOURCE GROUP `g` TYPE = USER VCPU = 2-3, 5 ENABLE', $statement->withCpus([new CpuRange(2, 3), new CpuRange(5, 5)])->toString());
        self::assertCount(1, $statement->cpus);
    }

    public function testWithPriorityReplacesThePriority(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP g TYPE = USER');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $statement);
        self::assertSame(7, $statement->withPriority(7)->priority);
        self::assertNull($statement->priority);
    }

    public function testWithStateCreatesTheGroupDisabled(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE RESOURCE GROUP g TYPE = USER');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $statement);
        self::assertSame(ResourceGroupState::Disabled, $statement->withState(ResourceGroupState::Disabled)->state);
    }

    public function testRejectsReleasesBeforeResourceGroups(): void
    {
        $old = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('DO 1');
        $this->expectException(InvalidStructure::class);
        new CreateResourceGroupStatement($old->origin, 'g', ThreadCategory::User);
    }
}
