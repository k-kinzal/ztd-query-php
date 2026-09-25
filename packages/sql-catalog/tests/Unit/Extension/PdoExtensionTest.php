<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\PdoExtension;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;

#[CoversClass(PdoExtension::class)]
#[UsesClass(SinkSpec::class)]
final class PdoExtensionTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('pdo', (new PdoExtension())->name());
    }

    public function testDescriptionMentionsWhatItCovers(): void
    {
        self::assertStringContainsString('PDO', (new PdoExtension())->description());
    }

    public function testSinksCoverQueryingPreparingAndBinding(): void
    {
        $sinks = (new PdoExtension())->sinks();
        $roles = array_map(static fn (SinkSpec $sink): SinkRole => $sink->role, $sinks);
        self::assertContains(SinkRole::Query, $roles);
        self::assertContains(SinkRole::Prepare, $roles);
        self::assertContains(SinkRole::Execute, $roles);
        self::assertContains(SinkRole::Bind, $roles);
        self::assertSame(['pdo.query', 'pdo.exec', 'pdo.prepare', 'pdo.statement.execute', 'pdo.statement.bindValue', 'pdo.statement.bindParam'], array_map(
            static fn (SinkSpec $sink): string => $sink->id,
            $sinks,
        ));
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
            (new PdoExtension())->sinks(),
        );

        self::assertSame(
            [
            'pdo.query|method|PDO|query|query|0|-|-|-|-|-/-',
            'pdo.exec|method|PDO|exec|query|0|-|-|-|-|-/-',
            'pdo.prepare|method|PDO|prepare|prepare|0|-|-|-|-|-/PDOStatement',
            'pdo.statement.execute|method|PDOStatement|execute|execute|-|0|-|-|-|-/-',
            'pdo.statement.bindValue|method|PDOStatement|bindValue|bind|-|-|-|0|1|-/-',
            'pdo.statement.bindParam|method|PDOStatement|bindParam|bind|-|-|-|0|1|-/-',
            ],
            $described,
        );
    }

    public function testGlobalsAreEmptyBecauseTheHandleIsNotReachedThroughOne(): void
    {
        self::assertSame([], (new PdoExtension())->globals());
    }
}
