<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\LaravelExtension;
use SqlCatalog\Extension\SinkCallKind;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Sql\StatementKind;

#[CoversClass(LaravelExtension::class)]
#[UsesClass(SinkSpec::class)]
final class LaravelExtensionTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('laravel', (new LaravelExtension())->name());
    }

    public function testDescriptionMentionsWhatItCovers(): void
    {
        self::assertStringContainsString('Laravel', (new LaravelExtension())->description());
    }

    public function testSinksCoverBothTheFacadeAndTheConnection(): void
    {
        $kinds = array_map(static fn (SinkSpec $sink): SinkCallKind => $sink->callKind, (new LaravelExtension())->sinks());
        self::assertContains(SinkCallKind::StaticCall, $kinds);
        self::assertContains(SinkCallKind::Method, $kinds);
    }

    public function testSinksTakeTheStatementFirstAndTheBindingsSecond(): void
    {
        $sinks = (new LaravelExtension())->sinks();
        self::assertSame(0, $sinks[0]->sqlParameter);
        self::assertSame(1, $sinks[0]->valuesParameter);
    }

    public function testMethodsNameTheKindEachOneImplies(): void
    {
        $methods = (new LaravelExtension())->methods();
        self::assertSame(StatementKind::Select, $methods['select']);
        self::assertSame(StatementKind::Insert, $methods['insert']);
        self::assertNull($methods['statement']);
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
            array_values(array_filter((new LaravelExtension())->sinks(), static fn (SinkSpec $sink): bool => $sink->role === \SqlCatalog\Extension\SinkRole::Query)),
        );

        self::assertSame(
            [
            'laravel.db.select|static|Illuminate\Support\Facades\DB|select|query|0|1|-|-|-|select/-',
            'laravel.connection.select|method|Illuminate\Database\Connection|select|query|0|1|-|-|-|select/-',
            'laravel.db.selectOne|static|Illuminate\Support\Facades\DB|selectOne|query|0|1|-|-|-|select/-',
            'laravel.connection.selectOne|method|Illuminate\Database\Connection|selectOne|query|0|1|-|-|-|select/-',
            'laravel.db.scalar|static|Illuminate\Support\Facades\DB|scalar|query|0|1|-|-|-|select/-',
            'laravel.connection.scalar|method|Illuminate\Database\Connection|scalar|query|0|1|-|-|-|select/-',
            'laravel.db.insert|static|Illuminate\Support\Facades\DB|insert|query|0|1|-|-|-|insert/-',
            'laravel.connection.insert|method|Illuminate\Database\Connection|insert|query|0|1|-|-|-|insert/-',
            'laravel.db.update|static|Illuminate\Support\Facades\DB|update|query|0|1|-|-|-|update/-',
            'laravel.connection.update|method|Illuminate\Database\Connection|update|query|0|1|-|-|-|update/-',
            'laravel.db.delete|static|Illuminate\Support\Facades\DB|delete|query|0|1|-|-|-|delete/-',
            'laravel.connection.delete|method|Illuminate\Database\Connection|delete|query|0|1|-|-|-|delete/-',
            'laravel.db.statement|static|Illuminate\Support\Facades\DB|statement|query|0|1|-|-|-|-/-',
            'laravel.connection.statement|method|Illuminate\Database\Connection|statement|query|0|1|-|-|-|-/-',
            'laravel.db.unprepared|static|Illuminate\Support\Facades\DB|unprepared|query|0|1|-|-|-|-/-',
            'laravel.connection.unprepared|method|Illuminate\Database\Connection|unprepared|query|0|1|-|-|-|-/-',
            ],
            $described,
        );
    }

    public function testBuilderMethodsDeclareReadsWritesAndCompoundExecutions(): void
    {
        $methods = (new LaravelExtension())->builderMethods();
        self::assertCount(36, $methods);
        self::assertSame(StatementKind::Select, $methods['get']);
        self::assertSame(StatementKind::Insert, $methods['insert']);
        self::assertSame(StatementKind::Update, $methods['update']);
        self::assertSame(StatementKind::Delete, $methods['delete']);
        self::assertNull($methods['save']);
        $builders = array_filter((new LaravelExtension())->sinks(), static fn (SinkSpec $sink): bool => $sink->role === \SqlCatalog\Extension\SinkRole::Builder);
        self::assertCount(count($methods) * 4, $builders);
    }

    public function testGlobalsAreEmptyBecauseTheHandleIsNotReachedThroughOne(): void
    {
        self::assertSame([], (new LaravelExtension())->globals());
    }
}
