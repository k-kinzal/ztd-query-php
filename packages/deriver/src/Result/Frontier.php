<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * An unexplored dependency and the projections its residual affects.
 *
 * @visibility public
 * @example Inspecting the contract
 *     $at = new \Deriver\Reference\SourceRef('s', 'a.php', 0, 1);
 *     (new \Deriver\Result\Frontier('MISSING_CALL_MODEL', $at, 'remote'))->code // => 'MISSING_CALL_MODEL'
 */
final class Frontier
{
    /**
     * @param string $code code
     * @param SourceRef $at at
     * @param string $operation operation
     * @param list<string> $affectedProjections affectedProjections
     * @param list<string> $knownDependencies Named inputs behind the frontier: `global:<name>` for a global variable, the same key `Configuration::$environment` accepts, or `variable:<name>` for a function-local variable
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
}
