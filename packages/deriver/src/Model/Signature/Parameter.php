<?php

declare(strict_types=1);

namespace Deriver\Model\Signature;

use Deriver\Value\Term;

/**
 * One external API parameter; binding is performed by the core.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Model\Signature\Parameter('id', 'int'))->name // => 'id'
 */
final class Parameter
{
    /**
     * @param string $name name
     * @param string $type type
     * @param bool $byReference byReference
     * @param bool $variadic variadic
     * @param Term|null $default default
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'mixed',
        public readonly bool $byReference = false,
        public readonly bool $variadic = false,
        public readonly ?Term $default = null,
    ) {
    }
}
