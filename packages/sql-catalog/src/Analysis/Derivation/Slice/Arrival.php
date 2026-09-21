<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation\Slice;

use PhpParser\Node\FunctionLike;

/**
 * A path walked back from a call to the start of the body it is written in.
 *
 * @visibility root
 */
final class Arrival
{
    /**
     * @param FunctionLike|null $body The function or method the path reached the start of, or null for the file itself
     * @param Pending $path The path, with whatever it still needs from before the body started
     */
    public function __construct(
        public readonly ?FunctionLike $body,
        public readonly Pending $path,
    ) {
    }
}
