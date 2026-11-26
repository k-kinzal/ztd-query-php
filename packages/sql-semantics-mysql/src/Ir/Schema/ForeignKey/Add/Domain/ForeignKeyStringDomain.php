<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Compatible native string storage on the two ordered sides of a reference.
 * @visibility public
 * @example Pairing byte strings with different declared lengths
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind::Varbinary;
 *     $type = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage::class;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringDomain(new $type($kind, 20), new $type($kind, 30)))->childKeyBytes() // => 20
 */
final class ForeignKeyStringDomain
{
    use Snapshot;

    /**
     * Checks matching native collations for text and matching families for byte strings.
     */
    public function __construct(public readonly ForeignKeyStringStorage $child, public readonly ForeignKeyStringStorage $parent)
    {
        Check::input($child->collation === $parent->collation, 'Native foreign-key string fields need the same comparison collation.');
    }

    /**
     * Reports the physical width of the child field in a supporting key.
     */
    public function childKeyBytes(): int
    {
        return $this->child->keyBytes();
    }
}
