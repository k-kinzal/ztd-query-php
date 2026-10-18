<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;

/**
 * An unexplored dependency and the projections its residual affects.
 *
 * @visibility public
 * @example Inspecting the contract
 *     $at = new \Deriver\Deriver\Sources\SourceRef('s', 'a.php', 0, 1);
 *     (new \Deriver\Deriver\Evaluator\Evidence\Frontier('MISSING_CALL_MODEL', $at, 'remote'))->code // => 'MISSING_CALL_MODEL'
 */
final class Frontier
{
    /**
     * @param string $code code
     * @param SourceRef $at at
     * @param string $operation operation
     * @param list<string> $affectedProjections affectedProjections
     * @param list<string> $knownDependencies Named inputs behind the frontier: `global:<name>` identifies a global variable supplied under `$<name>` in DeriverConfig::$environment; `variable:<name>` identifies a function-local variable
     * @param Term|null $residual residual
     * @param string $missingCapability missingCapability
     */
    public function __construct(
        public readonly string $code,
        public readonly SourceRef $at,
        public readonly string $operation,
        public readonly array $affectedProjections = [],
        public readonly array $knownDependencies = [],
        public readonly ?Term $residual = null,
        public readonly string $missingCapability = '',
    ) {
    }
    /**
     * Preserves an incomplete declaration in evaluation diagnostics.
     */
    public static function fromDiagnostic(\Deriver\Deriver\Lowerer\Declaration\Diagnostic $diagnostic): self
    {
        return new self($diagnostic->code, $diagnostic->at, $diagnostic->operation, missingCapability: $diagnostic->missingCapability);
    }

}
