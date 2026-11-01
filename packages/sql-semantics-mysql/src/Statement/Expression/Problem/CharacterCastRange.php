<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A resolved character cast exceeds the unsigned 32-bit byte bound.
 *
 * The requested character count and resolved character set determine this
 * condition independently of the operand's value or the runtime packet limit.
 *
 * @visibility public
 * @example Describing a resolved character-width condition
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CharacterCastRange())->message() // => "char(n) value is out of range in 'cast_as_char'"
 */
final class CharacterCastRange implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the character target's byte-capacity constraint.
     */
    public function message(): string
    {
        return "char(n) value is out of range in 'cast_as_char'";
    }
}
