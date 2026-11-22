<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;

/**
 * One named window of a WINDOW clause.
 *
 * The selection that holds the definition derives its specification.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-functions-named-windows.html.
 *
 * @visibility SqlSemantics
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
