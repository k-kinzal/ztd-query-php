<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A numeric operand whose floating value exceeds the finite DOUBLE domain.
 *
 * The operand spelling is retained without its source statement or parser node.
 *
 * @visibility public
 * @example Retaining the overflowing numeric operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedNumberLiteral('2e381'))->text // => '2e381'
 */
final class RefusedNumberLiteral implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $text The unsigned floating literal's exact spelling
     */
    public function __construct(public readonly string $text)
    {
    }

    /**
     * Describes the operand's representation condition.
     */
    public function message(): string
    {
        return 'A floating literal exceeds the finite DOUBLE domain.';
    }
}
