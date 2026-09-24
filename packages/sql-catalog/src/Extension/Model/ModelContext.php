<?php

declare(strict_types=1);

namespace SqlCatalog\Extension\Model;

use SqlCatalog\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Php\ProgramIndex;

/**
 * Shared source metadata and bounded callback evaluation available to extensions.
 *
 * @visibility public
 *
 * @example Passing analysis services to an extension
 *     $index = new \SqlCatalog\Php\ProgramIndex();
 *     $budget = new \SqlCatalog\Analysis\EvaluationBudget();
 *     $names = new \SqlCatalog\Analysis\Derivation\FreeNames();
 *     $modified = new \SqlCatalog\Analysis\Derivation\ModifiedNames($names);
 *     $callbacks = new \SqlCatalog\Analysis\Derivation\Objects\CallbackEffects(new \SqlCatalog\Analysis\Derivation\Slice\BackwardSlicer(new \SqlCatalog\Analysis\Derivation\SourceTree([]), $budget, $names, $modified), new \SqlCatalog\Analysis\Derivation\SliceExecutor(new \SqlCatalog\Php\DeclaredGlobals(), new \SqlCatalog\Php\TypeReader(), $modified, new \SqlCatalog\Php\NodeText(), $budget));
 *     $modelContext = new \SqlCatalog\Extension\Model\ModelContext($index, $callbacks, 'sqlite');
 *     $modelContext->dialect // => 'sqlite'
 */
final class ModelContext
{
    /**
     * The services of the current analysis; no application code is executed.
     */
    public function __construct(public readonly ProgramIndex $index, public readonly CallbackEffects $callbacks, public readonly ?string $dialect = null)
    {
    }
}
