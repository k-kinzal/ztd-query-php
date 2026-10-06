<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A character set at a position that names one: a character set name or the keyword DEFAULT.
 *
 * Character set names are compared without regard to letter case; the name is
 * kept as written. The keyword `BINARY` at such a position is the name
 * `binary`.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-charsets.html.
 *
 * @visibility public
 * @example Holding a character set name and the default
 *     $named = new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4'));
 *     [$named->name?->value, (new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(null))->name] // => ['utf8mb4', null]
 */
final class CharsetName implements Node
{
    use Snapshot;

    /**
     * @param Name|null $name The character set name, or null for the keyword DEFAULT
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
