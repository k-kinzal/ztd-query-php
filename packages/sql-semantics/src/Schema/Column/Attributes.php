<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Declared presentation and storage properties of one column.
 *
 * @visibility public
 */
final class Attributes
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly ?\SqlSemantics\Model\Relation\QualifiedName $collation = null,
        public readonly ?string $characterSet = null,
        public readonly ?string $comment = null,
        public readonly ?bool $visible = null,
        public readonly ?Storage $storage = null,
        public readonly ?Format $format = null,
        public readonly ?string $compression = null,
        public readonly ?string $engineAttribute = null,
        public readonly ?string $secondaryEngineAttribute = null,
        public readonly ?int $spatialReferenceId = null,
        public readonly bool $zeroFill = false,
        public readonly bool $binary = false,
    ) {
    }
}
