<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Sequence;

/**
 * Whether a new sequence is permanent, dropped at session end, or excluded from the write-ahead log.
 * LOCAL and GLOBAL are noise words for a temporary sequence.
 * @visibility public
 * @example Reading the SQL spelling of a temporary sequence
 *     \SqlSemantics\Model\Statement\Definition\Sequence\SequencePersistence::Temporary->value // => 'TEMPORARY'
 */
enum SequencePersistence: string
{
    case Permanent = '';
    case Temporary = 'TEMPORARY';
    case Unlogged = 'UNLOGGED';
}
