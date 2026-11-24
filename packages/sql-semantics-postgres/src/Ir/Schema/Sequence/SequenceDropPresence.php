<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

/**
 * The established occupancy of a sequence removal selector.
 *
 * @visibility public
 * @example Choosing an existing sequence
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceDropPresence::Existing->value // => 'existing'
 */
enum SequenceDropPresence: string
{
    case Existing = 'existing';
    case MissingSequence = 'missing-sequence';
    case MissingNamespace = 'missing-namespace';
}
