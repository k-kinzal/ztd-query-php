<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Two physical field encodings joined by a native scalar comparison.
 * @visibility public
 * @example Using byte comparison between a bit field and a binary field
 *     $bit = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage(\SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind::Bit, 9);
 *     $binary = new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage(\SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind::Varbinary, 3);
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarDomain($bit, $binary))->childKeyBytes() // => 2
 */
final class ForeignKeyScalarDomain
{
    use Snapshot;

    /**
     * Checks the compatible native comparison family and retains both side encodings.
     */
    public function __construct(
        public readonly ForeignKeyScalarStorage|ForeignKeyIntegerDomain|ForeignKeyStringStorage $child,
        public readonly ForeignKeyScalarStorage|ForeignKeyIntegerDomain|ForeignKeyStringStorage $parent,
    ) {
        $compatible = $child instanceof ForeignKeyScalarStorage
            ? $child->kind->accepts($parent)
            : $parent instanceof ForeignKeyScalarStorage && $parent->kind->accepts($child);
        Check::input($compatible, 'A native foreign-key pair needs compatible checked scalar field encodings.');
    }

    /**
     * Reports the width of the full referencing index term.
     */
    public function childKeyBytes(): int
    {
        return $this->child instanceof ForeignKeyIntegerDomain ? $this->child->childKeyBytes() : $this->child->keyBytes();
    }

}
