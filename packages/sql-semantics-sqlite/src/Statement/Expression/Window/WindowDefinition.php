<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression\Window;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * A named window of the WINDOW clause of a selection.
 *
 * Source: https://sqlite.org/windowfunctions.html#window_chaining.
 *
 * @visibility public
 * @example Reading a named window
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT rank() OVER w FROM t WINDOW w AS (ORDER BY a)');
 *     [$query->statement->windows[0]->name->value, count($query->statement->windows[0]->window->order)] // => ['w', 1]
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
