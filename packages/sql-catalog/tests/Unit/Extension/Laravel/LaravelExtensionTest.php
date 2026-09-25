<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\SinkCallKind;
use SqlCatalog\Core\Extension\SinkSpec;
use SqlCatalog\Core\Sql\StatementKind;
use SqlCatalog\Extension\Laravel\LaravelExtension;

#[CoversClass(LaravelExtension::class)]
#[UsesClass(SinkSpec::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderCalls::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\BuilderQueries::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallModel::class)]
#[UsesClass(\SqlCatalog\Extension\Laravel\CallbackModel::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelContext::class)]
#[UsesClass(\SqlCatalog\Core\Extension\Model\ModelSet::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
final class LaravelExtensionTest extends TestCase
{
    public function testNameIsHowTheCommandLineSelectsIt(): void
    {
        self::assertSame('laravel', (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->name());
    }

    public function testDescriptionMentionsWhatItCovers(): void
    {
        self::assertStringContainsString('Laravel', (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->description());
    }

    public function testSinksCoverBothTheFacadeAndTheConnection(): void
    {
        $kinds = array_map(static fn (SinkSpec $sink): SinkCallKind => $sink->callKind, (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks());
        self::assertContains(SinkCallKind::StaticCall, $kinds);
        self::assertContains(SinkCallKind::Method, $kinds);
    }

    public function testSinksTakeTheStatementFirstAndTheBindingsSecond(): void
    {
        $sinks = (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks();
        self::assertSame(0, $sinks[0]->sqlParameter);
        self::assertSame(1, $sinks[0]->valuesParameter);
    }

    public function testMethodsNameTheKindEachOneImplies(): void
    {
        $methods = (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->methods();
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
            array_values(array_filter((new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks(), static fn (SinkSpec $sink): bool => $sink->role === \SqlCatalog\Core\Extension\SinkRole::Query)),
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
        $methods = (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->builderMethods();
        self::assertCount(37, $methods);
        self::assertSame(StatementKind::Select, $methods['sole']);
        self::assertSame(StatementKind::Select, $methods['get']);
        self::assertSame(StatementKind::Insert, $methods['insert']);
        self::assertSame(StatementKind::Update, $methods['update']);
        self::assertSame(StatementKind::Delete, $methods['delete']);
        self::assertNull($methods['save']);
        $builders = array_filter((new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->sinks(), static fn (SinkSpec $sink): bool => $sink->role === \SqlCatalog\Core\Extension\SinkRole::Modelled);
        self::assertCount(count($methods) * 4, $builders);
    }

    public function testGlobalsAreEmptyBecauseTheHandleIsNotReachedThroughOne(): void
    {
        self::assertSame([], (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->globals());
    }
    public function testModelsRegistersLaravelSemanticsThroughTheOptionalContract(): void
    {
        $index = new \SqlCatalog\Core\Php\ProgramIndex();
        $budget = new \SqlCatalog\Core\Analysis\EvaluationBudget();
        $names = new \SqlCatalog\Core\Analysis\Derivation\FreeNames();
        $modified = new \SqlCatalog\Core\Analysis\Derivation\ModifiedNames($names);
        $callbacks = new \SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects(new \SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer(new \SqlCatalog\Core\Analysis\Derivation\SourceTree([]), $budget, $names, $modified), new \SqlCatalog\Core\Analysis\Derivation\SliceExecutor(new \SqlCatalog\Core\Php\DeclaredGlobals(), new \SqlCatalog\Core\Php\TypeReader(), $modified, new \SqlCatalog\Core\Php\NodeText(), $budget));
        $context = new \SqlCatalog\Core\Extension\Model\ModelContext($index, $callbacks, 'sqlite');
        $models = (new LaravelExtension(\SqlCatalog\Facade\Builtins::dialects()))->models($context);
        self::assertCount(1, $models->calls);
        self::assertArrayHasKey('laravel.builder', $models->queries);
        self::assertTrue($models->matchesClass('Illuminate\\Database\\MySqlConnection', 'Illuminate\\Database\\Connection'));
    }

}
