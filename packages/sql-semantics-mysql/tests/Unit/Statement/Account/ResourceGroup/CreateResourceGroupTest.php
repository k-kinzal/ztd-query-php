<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\ResourceGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupKind;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName;
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ThreadPriority;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CreateResourceGroup::class)]
#[Medium]
final class CreateResourceGroupTest extends TestCase
{
    /**
     * @return iterable<string, array{ResourceGroupKind, bool, string}>
     */
    public static function providerEnvironmentDependentPriorities(): iterable
    {
        yield 'positive system' => [ResourceGroupKind::System, false, '5'];
        yield 'low system' => [ResourceGroupKind::System, true, '21'];
        yield 'negative user' => [ResourceGroupKind::User, true, '1'];
        yield 'high user' => [ResourceGroupKind::User, false, '20'];
        yield 'leading zeros' => [ResourceGroupKind::User, false, '00000020'];
    }

    #[DataProvider('providerEnvironmentDependentPriorities')]
    public function testPriorityRetainsValuesWhoseAcceptanceDependsOnTheEnvironment(ResourceGroupKind $kind, bool $negative, string $number): void
    {
        $priority = new ThreadPriority($negative, new Numeral($number));
        $statement = new CreateResourceGroup(new ResourceGroupName(new Name('g')), $kind, priority: $priority);

        self::assertSame($priority, $statement->priority);
    }

    public function testDeriveStatementAcceptsTheBoundaryOfTheSystemRange(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE RESOURCE GROUP g TYPE = SYSTEM THREAD_PRIORITY -20')->diagnostics());
    }

    public function testRenderWritesEveryOption(): void
    {
        self::assertSame('CREATE RESOURCE GROUP g TYPE = SYSTEM VCPU = 1 THREAD_PRIORITY = 0 ENABLE', (new Semantics(Dialect::MySql))->analyze('create resource group g type system vcpu = 1 thread_priority 0 enable')->toString());
    }
    public function testDeriveStatementDoesNotDecidePlatformPrioritySupport(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE RESOURCE GROUP g TYPE USER THREAD_PRIORITY 20')->diagnostics());
    }

}
