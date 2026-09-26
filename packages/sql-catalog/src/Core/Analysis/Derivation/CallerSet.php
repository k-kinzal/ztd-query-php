<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\Derivation;

use PhpParser\Node\Expr;

/**
 * The calls found to reach a body, and whether some other call might reach it too.
 *
 * A call written with the method's name on something whose class could not be
 * worked out may or may not reach the method. Taking it as a caller would let
 * an unrelated method that happens to share the name — `DateTime::format`, a
 * class outside the analyzed files — pass its arguments in; leaving it out
 * silently would claim every caller was asked. So it is left out, and the set
 * says that it is partial.
 *
 * @visibility root
 */
final class CallerSet
{
    /**
     * @param list<Expr\CallLike> $calls The calls known to reach the body, in the order they are written
     * @param bool $partial Whether a call that might also reach the body could not be confirmed to
     */
    public function __construct(
        public readonly array $calls,
        public readonly bool $partial = false,
    ) {
    }
}
