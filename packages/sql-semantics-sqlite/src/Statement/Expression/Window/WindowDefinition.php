<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A named window of the WINDOW clause of a selection.
 *
 * Source: https://sqlite.org/windowfunctions.html#window_chaining.
 *
 * @visibility SqlSemantics
 */
final class WindowDefinition implements Node
{
    use Snapshot;

    /**
     * @param Name $name The window name
     * @param WindowSpec $window The specification
     */
    public function __construct(public readonly Name $name, public readonly WindowSpec $window)
    {
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label)->keyword('AS')->symbol('(')->node($this->window)->symbol(')');
    }
}
