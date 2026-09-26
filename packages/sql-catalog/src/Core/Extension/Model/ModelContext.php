<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Extension\Model;

use SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects;
use SqlCatalog\Core\Php\ProgramIndex;

/**
 * Shared source metadata and bounded callback evaluation available to extensions.
 *
 * @visibility public
 *
 * @example Passing analysis services to an extension
 *     $index = new \SqlCatalog\Core\Php\ProgramIndex();
 *     $budget = new \SqlCatalog\Core\Analysis\EvaluationBudget();
 *     $names = new \SqlCatalog\Core\Analysis\Derivation\FreeNames();
 *     $modified = new \SqlCatalog\Core\Analysis\Derivation\ModifiedNames($names);
 *     $callbacks = new \SqlCatalog\Core\Analysis\Derivation\Objects\CallbackEffects(new \SqlCatalog\Core\Analysis\Derivation\Slice\BackwardSlicer(new \SqlCatalog\Core\Analysis\Derivation\SourceTree([]), $budget, $names, $modified), new \SqlCatalog\Core\Analysis\Derivation\SliceExecutor(new \SqlCatalog\Core\Php\DeclaredGlobals(), new \SqlCatalog\Core\Php\TypeReader(), $modified, new \SqlCatalog\Core\Php\NodeText(), $budget));
 *     $modelContext = new \SqlCatalog\Core\Extension\Model\ModelContext($index, $callbacks, 'application');
 *     $modelContext->dialect // => 'application'
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
