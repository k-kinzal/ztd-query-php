<?php

declare(strict_types=1);

namespace Deriver\Model\Provider;

use Deriver\Project\TargetProfile;
use Deriver\Reference\SourceRef;
use Deriver\Value\Term;

/**
 * Evaluated receiver and method metadata supplied to a trusted dispatch provider.
 * @visibility public
 * @example A dispatch decision keeps its completeness explicit
 *     (new \Deriver\Model\Provider\DispatchDecision())->exhaustive // => false
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
