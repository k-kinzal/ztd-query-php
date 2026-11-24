<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

/**
 * The lifetime and logging behavior of a sequence.
 *
 * @visibility public
 * @example Selecting an unlogged sequence
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePersistence::Unlogged->value // => 'UNLOGGED'
 */
enum SequencePersistence: string
{
    case Permanent = '';
    case Unlogged = 'UNLOGGED';
    case Temporary = 'TEMPORARY';
    case GlobalTemporary = 'GLOBAL TEMPORARY';

    /**
     * Tests whether the declaration belongs to the current temporary namespace.
     */
    public function temporary(): bool
    {
        return $this === self::Temporary || $this === self::GlobalTemporary;
    }
}
