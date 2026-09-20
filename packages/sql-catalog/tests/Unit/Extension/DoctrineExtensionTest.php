<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\DoctrineExtension;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Sql\StatementKind;

#[CoversClass(DoctrineExtension::class)]
#[UsesClass(SinkSpec::class)]
final class DoctrineExtensionTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('doctrine', (new DoctrineExtension())->name());
    }

    public function testDescriptionMentionsWhatItCovers(): void
    {
        self::assertStringContainsString('Doctrine', (new DoctrineExtension())->description());
    }

    public function testSinksCoverPreparingBindingAndQuerying(): void
    {
        $roles = array_map(static fn (SinkSpec $sink): SinkRole => $sink->role, (new DoctrineExtension())->sinks());
        self::assertContains(SinkRole::Prepare, $roles);
        self::assertContains(SinkRole::Bind, $roles);
        self::assertContains(SinkRole::Query, $roles);
    }

    public function testPreparingHandsBackADoctrineStatement(): void
    {
        $prepare = array_values(array_filter(
            (new DoctrineExtension())->sinks(),
            static fn (SinkSpec $sink): bool => $sink->id === 'doctrine.prepare',
        ));
        self::assertSame('Doctrine\\DBAL\\Statement', $prepare[0]->handleType);
    }

    public function testMethodsNameTheKindTheFetchCallsImply(): void
    {
        $methods = (new DoctrineExtension())->methods();
        self::assertSame(StatementKind::Select, $methods['fetchAllAssociative']);
        self::assertNull($methods['executeStatement']);
    }
    public function testEverySinkIsDescribedExactly(): void
    {
        $described = array_map(
            static fn (SinkSpec $sink): string => implode('|', [
                $sink->id,
                $sink->callKind->value,
                $sink->receiverType ?? '-',
                $sink->name,
                $sink->role->value,
                $sink->sqlParameter ?? '-',
                $sink->valuesParameter ?? '-',
                $sink->valuesFrom ?? '-',
                $sink->nameParameter ?? '-',
                $sink->valueParameter ?? '-',
                ($sink->kind === null ? '-' : $sink->kind->value) . '/' . ($sink->handleType ?? '-'),
            ]),
            (new DoctrineExtension())->sinks(),
        );

        self::assertSame(
            [
            'doctrine.prepare|method|Doctrine\DBAL\Connection|prepare|prepare|0|-|-|-|-|-/Doctrine\DBAL\Statement',
            'doctrine.statement.bindValue|method|Doctrine\DBAL\Statement|bindValue|bind|-|-|-|0|1|-/-',
            'doctrine.statement.executeQuery|method|Doctrine\DBAL\Statement|executeQuery|execute|-|0|-|-|-|-/-',
            'doctrine.executeQuery|method|Doctrine\DBAL\Connection|executeQuery|query|0|1|-|-|-|-/-',
            'doctrine.executeStatement|method|Doctrine\DBAL\Connection|executeStatement|query|0|1|-|-|-|-/-',
            'doctrine.executeCacheQuery|method|Doctrine\DBAL\Connection|executeCacheQuery|query|0|1|-|-|-|select/-',
            'doctrine.fetchAllAssociative|method|Doctrine\DBAL\Connection|fetchAllAssociative|query|0|1|-|-|-|select/-',
            'doctrine.fetchAllKeyValue|method|Doctrine\DBAL\Connection|fetchAllKeyValue|query|0|1|-|-|-|select/-',
            'doctrine.fetchAllNumeric|method|Doctrine\DBAL\Connection|fetchAllNumeric|query|0|1|-|-|-|select/-',
            'doctrine.fetchAssociative|method|Doctrine\DBAL\Connection|fetchAssociative|query|0|1|-|-|-|select/-',
            'doctrine.fetchNumeric|method|Doctrine\DBAL\Connection|fetchNumeric|query|0|1|-|-|-|select/-',
            'doctrine.fetchFirstColumn|method|Doctrine\DBAL\Connection|fetchFirstColumn|query|0|1|-|-|-|select/-',
            'doctrine.fetchOne|method|Doctrine\DBAL\Connection|fetchOne|query|0|1|-|-|-|select/-',
            'doctrine.iterateAssociative|method|Doctrine\DBAL\Connection|iterateAssociative|query|0|1|-|-|-|select/-',
            ],
            $described,
        );
    }

    public function testGlobalsAreEmptyBecauseTheHandleIsNotReachedThroughOne(): void
    {
        self::assertSame([], (new DoctrineExtension())->globals());
    }
}
