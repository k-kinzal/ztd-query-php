<?php

declare(strict_types=1);

namespace Deriver\Candidate;

use Deriver\Query\Query;
use Deriver\Reference\PointRef;
use Deriver\Reference\SourceRef;
use Deriver\Value\Projection;

/**
 * A validated query bound to one point of the captured program: the artifact of target selection.
 * Later stages read the owner, kind and selected registers from here and never inspect the query's class again.
 * @visibility namespace
 * @visibility Deriver\Analysis
 */
final class Target
{
    /**
     * @param Query $query The validated query
     * @param string $owner Callable that owns the selected definition
     * @param 'parameter'|'return'|'value'|'tuple'|'state' $kind Which definition the query selects
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
        public readonly Query $query,
        public readonly string $owner,
        public readonly string $kind,
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
