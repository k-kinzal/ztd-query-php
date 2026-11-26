<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * One exact storage declaration participating in native string-key comparison.
 * @visibility public
 * @example Measuring the full physical byte width of a text key
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringKind::Varchar;
 *     $collation = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation::Binary;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyStringStorage($kind, 8, $collation))->keyBytes() // => 32
 */
final class ForeignKeyStringStorage
{
    use Snapshot;

    /**
     * Checks full, nonzero physical fields and their exact comparison collation.
     */
    public function __construct(
        public readonly ForeignKeyStringKind $kind,
        public readonly int $length,
        public readonly ?ForeignKeyTextCollation $collation = null,
    ) {
        Check::input($kind->text() === ($collation !== null), 'A native string key requires the collation of its declared storage family.');
        $maximum = $kind->fixed() ? 255 : ($kind->text() ? 16383 : 65535);
        Check::input($length > 0 && $length <= $maximum, 'A native string key needs a nonzero field length within its storage family.');
    }

    /**
     * Reports bytes contributed by the full declared field to a physical key.
     */
    public function keyBytes(): int
    {
        return $this->length * ($this->kind->text() ? 4 : 1);
    }
}
