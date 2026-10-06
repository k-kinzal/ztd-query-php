<?php

declare(strict_types=1);

namespace Deriver\ControlFlow;

/**
 * A once-evaluated argument and its address, when addressable.
 *
 * @visibility root
 */
final class Argument
{
    /**
     * @param string $register register
     * @param string|null $name name
     * @param bool $unpack unpack
     * @param string|null $location location
     */
    public function __construct(
        public readonly string $register,
        public readonly ?string $name = null,
        public readonly bool $unpack = false,
        public readonly ?string $location = null,
    ) {
    }
}
