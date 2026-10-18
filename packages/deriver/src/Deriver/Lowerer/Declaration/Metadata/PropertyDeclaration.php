<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration\Metadata;

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
     * @param Visibility $visibility visibility
     * @param bool $static static
     * @param \Deriver\Deriver\Lowerer\Graph\CallableGraph|null $default default
     * @param bool $readonly Whether reassignment is forbidden
     * @param string $docComment Raw declaration doc comment, or an empty string
     * @param \Deriver\Deriver\Lowerer\Graph\CallableGraph|null $get Getter body
     * @param \Deriver\Deriver\Lowerer\Graph\CallableGraph|null $set Setter body
     */
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly string $type = 'mixed',
        public readonly Visibility $visibility = Visibility::Public,
        public readonly bool $static = false,
        public readonly ?\Deriver\Deriver\Lowerer\Graph\CallableGraph $default = null,
        public readonly bool $readonly = false,
        public readonly string $docComment = '',
        public readonly ?\Deriver\Deriver\Lowerer\Graph\CallableGraph $get = null,
        public readonly ?\Deriver\Deriver\Lowerer\Graph\CallableGraph $set = null,
    ) {
    }
}
