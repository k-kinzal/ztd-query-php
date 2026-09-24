<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ResourceGroup\CpuRange;
use SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\ResourceGroups;

#[CoversClass(ResourceGroups::class)]
#[Medium]
final class ResourceGroupsTest extends TestCase
{
    public function testWriteSpellsOptionsWithEqualsAndOmitsUnchangedOnes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $create = $binder->bind('CREATE RESOURCE GROUP g TYPE USER VCPU 1 THREAD_PRIORITY 2');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $create);
        self::assertSame('CREATE RESOURCE GROUP `g` TYPE = USER VCPU = 1 THREAD_PRIORITY = 2 ENABLE', ResourceGroups::write($create)->toString());
        self::assertSame('ALTER RESOURCE GROUP `g` FORCE', $binder->bind('ALTER RESOURCE GROUP g FORCE')->toString());
        self::assertSame('SET RESOURCE GROUP `g`', $binder->bind('SET RESOURCE GROUP g')->toString());
    }

    public function testCpusSpellsASingleCpuWithoutARange(): void
    {
        self::assertSame(['3', '0-2'], [ResourceGroups::cpus(new CpuRange(3, 3))->toString(), ResourceGroups::cpus(new CpuRange(0, 2))->toString()]);
    }
}
