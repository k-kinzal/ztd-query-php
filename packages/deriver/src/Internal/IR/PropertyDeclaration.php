<?php

declare(strict_types=1);

namespace Deriver\Internal\IR;

/**
 * A property slot with its declaration scope and initialization expression.
 *
 * @visibility root
 */
final class PropertyDeclaration
{
    /**
     * @param string $name name
     * @param string $className className
     * @param string $type type
     * @param string $visibility visibility
     * @param bool $static static
     * @param CallableIR|null $default default
     * @param bool $readonly Whether reassignment is forbidden
     */
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly string $type = 'mixed',
        public readonly string $visibility = 'public',
        public readonly bool $static = false,
        public readonly ?CallableIR $default = null,
        public readonly bool $readonly = false,
    ) {
    }
}
