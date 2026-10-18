<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Evaluated receiver and method metadata supplied to a trusted dispatch provider.
 * @visibility root
 * @example A dispatch decision keeps its completeness explicit
 *     (new \Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision())->exhaustive // => false
 */
final class DispatchRequest
{
    /**
     * @param Term $receiver Evaluated receiver
     * @param string $method Known method name
     * @param bool $static Whether this is a static call
     * @param SourceRef $source Call-site provenance
     * @param TargetProfile $target Semantic target
     */
    public function __construct(public readonly Term $receiver, public readonly string $method, public readonly bool $static, public readonly SourceRef $source, public readonly TargetProfile $target)
    {
    }
}
