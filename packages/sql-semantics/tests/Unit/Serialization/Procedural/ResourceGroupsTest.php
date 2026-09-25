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
        self::assertSame('ALTER RESOURCE GROUP `g` FORCE', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('ALTER RESOURCE GROUP g FORCE')));
        self::assertSame('SET RESOURCE GROUP `g`', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind('SET RESOURCE GROUP g')));
    }

    public function testCpusSpellsASingleCpuWithoutARange(): void
    {
        self::assertSame(['3', '0-2'], [ResourceGroups::cpus(new CpuRange(3, 3))->toString(), ResourceGroups::cpus(new CpuRange(0, 2))->toString()]);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['create resource group g type = user vcpu = 0-1, 3 thread_priority = 5 disable', 'CREATE RESOURCE GROUP `g` TYPE = USER VCPU = 0-1, 3 THREAD_PRIORITY = 5 DISABLE'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['alter resource group g vcpu = 2 thread_priority = 1 enable force', 'ALTER RESOURCE GROUP `g` VCPU = 2 THREAD_PRIORITY = 1 ENABLE FORCE'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['set resource group g for 1, 2', 'SET RESOURCE GROUP `g` FOR 1, 2'])]
    public function testWriteSpellsEveryResourceGroupClause(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql)));
    }
}
