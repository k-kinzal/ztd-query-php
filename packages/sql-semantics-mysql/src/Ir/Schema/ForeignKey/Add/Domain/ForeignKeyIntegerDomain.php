<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain;

use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The common native integer encoding on both sides of a foreign-key pair.
 * @visibility public
 * @example Measuring the integer storage of the pair
 *     $width = \SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage::Medium;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain($width, true))->childKeyBytes() // => 3
 */
final class ForeignKeyIntegerDomain
{
    use Snapshot;

    /**
     * Fixes the equal native width and signedness of the two fields.
     */
    public function __construct(public readonly IntegerStorage $storage, public readonly bool $unsigned)
    {
    }

    /**
     * Reports bytes contributed by the child field to a full key.
     */
    public function childKeyBytes(): int
    {
        return $this->storage->bytes();
    }
}
