<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A collation at a position that names one: a collation name or the keyword DEFAULT.
 *
 * Collation names are compared without regard to letter case; the name is
 * kept as written. The keyword `BINARY` at such a position is the collation name
 * `binary`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-names.html.
 *
 * @visibility public
 * @example Holding a collation name and the default
 *     $named = new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_bin'));
 *     [$named->name?->value, (new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(null))->name] // => ['utf8mb4_bin', null]
 */
final class CollationName implements Node
{
    use Snapshot;

    /**
     * @param Name|null $name The collation name, or null for the keyword DEFAULT
     */
    public function __construct(public readonly ?Name $name)
    {
    }

    /**
     * Writes the name or the keyword DEFAULT.
     */
    public function render(Output $out): void
    {
        if ($this->name === null) {
            $out->keyword('DEFAULT');
        } else {
            $out->name($this->name, NameUse::Label);
        }
    }
}
