<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Procedural\ResourceGroups;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Server\ResourceGroup\AlterResourceGroupStatement;
use SqlSemantics\Model\Statement\Server\ResourceGroup\CreateResourceGroupStatement;
use SqlSemantics\Model\Statement\Server\ResourceGroup\SetResourceGroupStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResourceGroups::class)]
#[Medium]
final class ResourceGroupsTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindReadsEachResourceGroupOperationAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $create = $binder->bind('CREATE RESOURCE GROUP `b g` TYPE := SYSTEM VCPU 0 - 3, 5 THREAD_PRIORITY = - 4 DISABLE');
        $alter = $binder->bind('ALTER RESOURCE GROUP g THREAD_PRIORITY 3 ENABLE FORCE');
        $set = $binder->bind('SET RESOURCE GROUP g FOR 7, 0x1F');
        self::assertInstanceOf(CreateResourceGroupStatement::class, $create);
        self::assertInstanceOf(AlterResourceGroupStatement::class, $alter);
        self::assertInstanceOf(SetResourceGroupStatement::class, $set);
        self::assertSame(['b g', 'SYSTEM', [[0, 3], [5, 5]], -4, 'DISABLE'], [$create->name, $create->type->value, array_map(static fn ($range): array => [$range->first, $range->last], $create->cpus), $create->priority, $create->state->value]);
        self::assertSame([3, 'ENABLE', true], [$alter->priority, $alter->state?->value, $alter->force]);
        self::assertSame(['7', '0x1F'], array_map(static fn ($thread): string => $thread->text, $set->threads));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($create), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($create))));
        self::assertSame('SET RESOURCE GROUP `g` FOR 7, 0x1F', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($set))));
    }

    #[TestWith(['CREATE RESOURCE GROUP g TYPE = USER THREAD_PRIORITY = -1'])]
    #[TestWith(['ALTER RESOURCE GROUP g THREAD_PRIORITY = 20'])]
    #[TestWith(['CREATE RESOURCE GROUP g TYPE = SYSTEM VCPU = 3-1'])]
    public function testBindDiagnosesOptionsTheServerRejects(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ResourceGroupOption->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testForceIsFalseWithoutTheKeyword(): void
    {
        $parser = new DialectParser(Dialect::MySql, 'mysql-8.4.7');
        self::assertTrue(ResourceGroups::force($parser->parse('ALTER RESOURCE GROUP g FORCE')->find('alter_resource_group_stmt')[0]));
        self::assertFalse(ResourceGroups::force($parser->parse('ALTER RESOURCE GROUP g')->find('alter_resource_group_stmt')[0]));
    }

    public function testCpusKeepsRequestOrder(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER RESOURCE GROUP g VCPU 4, 1-2')->find('alter_resource_group_stmt')[0];
        self::assertSame([[4, 4], [1, 2]], array_map(static fn ($range): array => [$range->first, $range->last], ResourceGroups::cpus($node)));
    }

    public function testThreadsKeepsTheLiteralSpelling(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('SET RESOURCE GROUP g FOR 007')->find('set_resource_group_stmt')[0];
        self::assertSame(['007'], array_map(static fn ($thread): string => $thread->text, ResourceGroups::threads($node)));
    }
}
