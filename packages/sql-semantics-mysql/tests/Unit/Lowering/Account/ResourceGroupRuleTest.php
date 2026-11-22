<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\ResourceGroupRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ResourceGroupRule::class)]
#[Medium]
final class ResourceGroupRuleTest extends TestCase
{
    public function testStatementLowersEveryStatement(): void
    {
        self::assertSame('SET RESOURCE GROUP g', (new Inspection())->inspect('set resource group g')->toString());
    }

    public function testCreateLowersTheType(): void
    {
        self::assertSame('CREATE RESOURCE GROUP g TYPE = USER', (new Inspection())->inspect('create resource group g type = user')->toString());
    }

    public function testCpusLowersTheVcpuList(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU = 1, 2, 3', (new Inspection())->inspect('alter resource group g vcpu = 1 2, 3')->toString());
    }

    public function testRangeLowersARange(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU = 1 - 4', (new Inspection())->inspect('alter resource group g vcpu 1-4')->toString());
    }

    public function testPriorityLowersTheSign(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g THREAD_PRIORITY = 2', (new Inspection())->inspect('alter resource group g thread_priority 2')->toString());
    }

    public function testEnabledLowersTheKeyword(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g DISABLE', (new Inspection())->inspect('alter resource group g disable')->toString());
    }

    public function testForceLowersTheKeyword(): void
    {
        self::assertSame('DROP RESOURCE GROUP g FORCE', (new Inspection())->inspect('drop resource group g force')->toString());
    }

    public function testThreadsLowersTheThreadList(): void
    {
        self::assertSame('SET RESOURCE GROUP g FOR 1, 2, 3', (new Inspection())->inspect('set resource group g for 1 2, 3')->toString());
    }

    public function testNumberKeepsTheText(): void
    {
        self::assertSame('ALTER RESOURCE GROUP g VCPU = 007', (new Inspection())->inspect('alter resource group g vcpu 007')->toString());
    }

    public function testClaimedConfirmsTheList(): void
    {
        self::assertSame('SET RESOURCE GROUP g FOR 9', (new Inspection())->inspect('set resource group g for 9')->toString());
    }
}
