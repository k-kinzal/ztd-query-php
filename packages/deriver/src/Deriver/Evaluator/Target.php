<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator;

use Deriver\Deriver\Lowerer\Graph\PointRef;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Selected definitions consumed by dependency derivation, independent of query syntax.
 * @visibility Deriver\Deriver
 */
final class Target
{
    /**
     * @param string $owner Callable that owns the selected definition
     * @param TargetKind $kind Which definition the query selects
     * @param string $selector Public name of the selected definition: register, parameter, variable or owner
     * @param SourceRef|null $source Source range of the selected expression or observation point, when the query names one
     * @param string|null $register Selected expression register
     * @param array<string, string> $registers Selected expression registers by tuple member name
     * @param string|null $parameter Selected formal parameter
     * @param PointRef|null $point Observation point of a storage selection
     * @param string|null $variable Selected local variable
     * @param Projection|null $projection Slot or path projection applied to the selected value
     */
    public function __construct(
        public readonly string $owner,
        public readonly TargetKind $kind,
        public readonly string $selector,
        public readonly ?SourceRef $source = null,
        public readonly ?string $register = null,
        public readonly array $registers = [],
        public readonly ?string $parameter = null,
        public readonly ?PointRef $point = null,
        public readonly ?string $variable = null,
        public readonly ?Projection $projection = null,
    ) {
    }
}
