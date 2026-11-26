<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Key;

use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Resolves a primary column's default before the complete table is published.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PrimaryColumnPolicy
{
    /**
     * Selects the applicable native rule for an inline or table key.
     */
    public function __construct(public readonly GrammarRelease $release, public readonly bool $inline)
    {
    }

    /**
     * Resolves the effective default of the now-required primary column.
     */
    public function effectiveDefault(
        ?bool $nullable,
        IntegerLiteral|RangeIntegerLiteral|TextLiteral|NullLiteral|null $default
    ): IntegerLiteral|RangeIntegerLiteral|TextLiteral|NullLiteral|null {
        if ($this->release !== GrammarRelease::MySql5651) {
            Check::input($nullable !== true && !$default instanceof NullLiteral, 'A primary key column cannot admit NULL.');
        } elseif (!$this->inline && $nullable !== false && ($default === null || $default instanceof NullLiteral)) {
            return new IntegerLiteral('0');
        }

        return $default;
    }
}
