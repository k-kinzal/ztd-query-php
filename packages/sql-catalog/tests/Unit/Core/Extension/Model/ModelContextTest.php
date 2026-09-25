<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Extension\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\Model\ModelContext;

#[CoversClass(ModelContext::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\FreeNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\ModifiedNames::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SliceExecutor::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\AssignmentSteps::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\Slice\LoopPasses::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\Derivation\SourceTree::class)]
#[UsesClass(\SqlCatalog\Core\Analysis\EvaluationBudget::class)]
#[UsesClass(\SqlCatalog\Core\Php\DeclaredGlobals::class)]
#[UsesClass(\SqlCatalog\Core\Php\NodeText::class)]
#[UsesClass(\SqlCatalog\Core\Php\ProgramIndex::class)]
final class ModelContextTest extends TestCase
{
    public function testKeepsTheModelContractInputs(): void
    {
        $index = new \SqlCatalog\Core\Php\ProgramIndex();
        $budget = new \SqlCatalog\Core\Analysis\EvaluationBudget();
        $names = new \SqlCatalog\Core\Analysis\Derivation\FreeNames();
        $modified = new \SqlCatalog\Core\Analysis\Derivation\ModifiedNames($names);
        $callbacks = new \SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects(new \SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer(new \SqlCatalog\Core\Analysis\Derivation\SourceTree([]), $budget, $names, $modified), new \SqlCatalog\Core\Analysis\Derivation\SliceExecutor(new \SqlCatalog\Core\Php\DeclaredGlobals(), new \SqlCatalog\Core\Php\TypeReader(), $modified, new \SqlCatalog\Core\Php\NodeText(), $budget));
        $context = new ModelContext($index, $callbacks, 'pgsql');
        self::assertSame($index, $context->index);
        self::assertSame($callbacks, $context->callbacks);
        self::assertSame('pgsql', $context->dialect);
    }
}
