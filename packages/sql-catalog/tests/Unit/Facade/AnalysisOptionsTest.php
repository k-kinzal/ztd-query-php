<?php

declare(strict_types=1);

namespace Tests\Unit\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Analysis\EvaluationBudget;
use SqlCatalog\Facade\AnalysisOptions;

#[CoversClass(AnalysisOptions::class)]
#[UsesClass(EvaluationBudget::class)]
final class AnalysisOptionsTest extends TestCase
{
    public function testTheDefaultsRecogniseTheDriverExtensions(): void
    {
        self::assertSame(['pdo', 'mysqli'], (new AnalysisOptions())->extensions);
    }

    public function testWithExtensionsKeepsTheBudget(): void
    {
        $budget = new EvaluationBudget(10);
        $options = (new AnalysisOptions(['pdo'], $budget))->withExtensions(['laravel']);
        self::assertSame(['laravel'], $options->extensions);
        self::assertSame($budget, $options->budget);
    }

    public function testBudgetFallsBackToTheDefault(): void
    {
        self::assertSame(20000, (new AnalysisOptions())->budget()->maxSteps);
    }

    public function testBudgetIsTheOneThatWasGiven(): void
    {
        $budget = new EvaluationBudget(10);
        self::assertSame($budget, (new AnalysisOptions(['pdo'], $budget))->budget());
    }

    public function testWithExtensionsPreservesTheConfiguredDialect(): void
    {
        self::assertSame('pgsql', (new AnalysisOptions(dialect: 'pgsql'))->withExtensions(['laravel'])->dialect);
    }


}
