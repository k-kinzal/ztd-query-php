<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Storage;

/**
 * A named storage parameter whose value is a literal or identifier.
 *
 * @visibility public
 */
final class Parameter
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly \SqlSemantics\Model\Relation\QualifiedName $name,
        public readonly \SqlSemantics\Model\Scalar\Value\Literal|\SqlSemantics\Model\Scalar\Value\ConfigurationIdentifier|\SqlSemantics\Model\Scalar\Value\ConfigurationKeyword $value,
    ) {
    }
}
