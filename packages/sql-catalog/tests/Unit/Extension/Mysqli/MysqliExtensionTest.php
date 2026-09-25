<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Mysqli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Extension\Mysqli\MysqliExtension;

#[CoversClass(MysqliExtension::class)]
#[UsesClass(SinkSpec::class)]
final class MysqliExtensionTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('mysqli', (new MysqliExtension())->name());
    }

    public function testDescriptionMentionsWhatItCovers(): void
    {
        self::assertStringContainsString('mysqli', (new MysqliExtension())->description());
    }

    public function testSinksCoverBothTheObjectAndTheProceduralApi(): void
    {
        $kinds = array_map(static fn (SinkSpec $sink): SinkCallKind => $sink->callKind, (new MysqliExtension())->sinks());
        self::assertContains(SinkCallKind::Method, $kinds);
        self::assertContains(SinkCallKind::FunctionCall, $kinds);
    }

    public function testMethodSinksBindVariadicValues(): void
    {
        $bind = array_values(array_filter(
            (new MysqliExtension())->methodSinks(),
            static fn (SinkSpec $sink): bool => $sink->id === 'mysqli.stmt.bind_param',
        ));
        self::assertSame(1, $bind[0]->valuesFrom);
    }

    public function testFunctionSinksTakeTheStatementAsTheSecondArgument(): void
    {
        $positions = array_map(
            static fn (SinkSpec $sink): ?int => $sink->sqlParameter,
            (new MysqliExtension())->functionSinks(),
        );
        self::assertSame([1], array_values(array_unique($positions)));
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
            (new MysqliExtension())->sinks(),
        );

        self::assertSame(
            [
            'mysqli.query|method|mysqli|query|query|0|-|-|-|-|-/-',
            'mysqli.real_query|method|mysqli|real_query|query|0|-|-|-|-|-/-',
            'mysqli.multi_query|method|mysqli|multi_query|query|0|-|-|-|-|-/-',
            'mysqli.execute_query|method|mysqli|execute_query|query|0|1|-|-|-|-/-',
            'mysqli.prepare|method|mysqli|prepare|prepare|0|-|-|-|-|-/mysqli_stmt',
            'mysqli.stmt.bind_param|method|mysqli_stmt|bind_param|bind|-|-|1|-|-|-/-',
            'mysqli.stmt.execute|method|mysqli_stmt|execute|execute|-|0|-|-|-|-/-',
            'mysqli.fn.query|function|-|mysqli_query|query|1|-|-|-|-|-/-',
            'mysqli.fn.real_query|function|-|mysqli_real_query|query|1|-|-|-|-|-/-',
            'mysqli.fn.multi_query|function|-|mysqli_multi_query|query|1|-|-|-|-|-/-',
            'mysqli.fn.execute_query|function|-|mysqli_execute_query|query|1|2|-|-|-|-/-',
            'mysqli.fn.prepare|function|-|mysqli_prepare|prepare|1|-|-|-|-|-/mysqli_stmt',
            ],
            $described,
        );
    }

    public function testGlobalsAreEmptyBecauseTheHandleIsNotReachedThroughOne(): void
    {
        self::assertSame([], (new MysqliExtension())->globals());
    }
}
