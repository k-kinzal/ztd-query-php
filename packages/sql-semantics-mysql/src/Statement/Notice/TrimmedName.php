<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * Leading nongraphic characters were removed from an explicit result alias.
 *
 * The full alias remains available even when a NUL ends its diagnostic display.
 * Observed on MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility public
 * @example Describing the removed prefix
 *     (new \SqlSemantics\Platform\MySql\Statement\Notice\TrimmedName(new \SqlSemantics\Statement\Identifier\Name(' name')))->message() // => "Leading spaces are removed from name ' name'"
 */
final class TrimmedName implements Warning
{
    use Snapshot;

    /**
     * @param Name $name The decoded explicit alias before normalization
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Returns the server warning number.
     */
    public function code(): int
    {
        return 1466;
    }

    /**
     * Describes the original alias up to its first NUL.
     */
    public function message(): string
    {
        return "Leading spaces are removed from name '" . explode("\0", $this->name->value, 2)[0] . "'";
    }
}
