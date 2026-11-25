<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

/**
 * The native key policy removed with a resolved index.
 *
 * @visibility public
 * @example Distinguishing a unique secondary index
 *     \SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole::Unique->value // => 'unique'
 */
enum IndexRole: string
{
    case Primary = 'primary';
    case ClusteredUnique = 'clustered-unique';
    case Unique = 'unique';
    case Ordinary = 'ordinary';

    /**
     * Answers whether removal changes the owning InnoDB table's clustered identity.
     */
    public function clustered(): bool
    {
        return match ($this) {
            self::Primary, self::ClusteredUnique => true,
            self::Unique, self::Ordinary => false,
        };
    }
}
