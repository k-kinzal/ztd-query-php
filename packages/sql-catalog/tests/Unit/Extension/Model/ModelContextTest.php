<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\Model\ModelContext;

#[CoversClass(ModelContext::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Objects\CallbackEffects::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Php\ProgramIndex::class)]
final class ModelContextTest extends TestCase
{
    public function testKeepsTheModelContractInputs(): void
    {
        $index = new \SqlCatalog\Php\ProgramIndex();
        $budget = new \SqlCatalog\Analysis\EvaluationBudget();
        $names = new \SqlCatalog\Analysis\Derivation\FreeNames();
        $modified = new \SqlCatalog\Analysis\Derivation\ModifiedNames($names);
        $callbacks = new \SqlCatalog\Analysis\Derivation\Objects\CallbackEffects(new \SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer(new \SqlCatalog\Analysis\Derivation\SourceTree([]), $budget, $names, $modified), new \SqlCatalog\Analysis\Derivation\SliceExecutor(new \SqlCatalog\Php\DeclaredGlobals(), new \SqlCatalog\Php\TypeReader(), $modified, new \SqlCatalog\Php\NodeText(), $budget));
        $context = new ModelContext($index, $callbacks, 'pgsql');
        self::assertSame($index, $context->index);
        self::assertSame($callbacks, $context->callbacks);
        self::assertSame('pgsql', $context->dialect);
    }
}
