<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence;

/**
 * The established occupancy of a sequence removal selector.
 *
 * @visibility public
 * @example Choosing an existing sequence
 *     \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequencePresence::Existing->value // => 'existing'
 */
enum SequencePresence: string
{
    case Existing = 'existing';
    case MissingSequence = 'missing-sequence';
    case MissingNamespace = 'missing-namespace';
}
