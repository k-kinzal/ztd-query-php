<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Sources\SourceRef;

/**
 * A declaration that cannot be represented completely by the lowerer.
 * @visibility root
 */
final class Diagnostic
{
    /**
     * Preserves the declaration location and unavailable capability.
     */
    public function __construct(public readonly string $code, public readonly SourceRef $at, public readonly string $operation, public readonly string $missingCapability = '')
    {
    }
}
