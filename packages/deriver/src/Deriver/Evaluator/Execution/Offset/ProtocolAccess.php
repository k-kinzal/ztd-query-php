<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Term;

/**
 * An ArrayAccess receiver and the remaining evaluated offset chain.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class ProtocolAccess
{
    /**
     * @param Term $receiver Evaluated object identity
     * @param Term|null $key Original key, without array-key conversion
     * @param list<string> $remaining Offset registers below the overloaded element
     */
    public function __construct(public readonly Term $receiver, public readonly ?Term $key, public readonly array $remaining = [])
    {
    }
}
