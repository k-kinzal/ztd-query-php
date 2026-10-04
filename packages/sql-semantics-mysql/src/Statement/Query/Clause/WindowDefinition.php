<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One named window of a WINDOW clause.
 *
 * The selection that holds the definition derives its specification.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 *
 * @visibility public
 * @example Reading the name of a window
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t WINDOW w AS (ORDER BY a)');
 *     $query->statement->windows[0]->name->value // => 'w'
 */
final class WindowDefinition implements Node
{
    use Snapshot;

    /**
     * @param Name $name The window name
     * @param WindowSpecification $specification The window
     */
    public function __construct(public readonly Name $name, public readonly WindowSpecification $specification)
    {
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Alias)->keyword('AS')->node($this->specification);
    }
}
