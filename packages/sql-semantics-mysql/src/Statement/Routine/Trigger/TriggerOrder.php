<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Trigger;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The FOLLOWS or PRECEDES clause of CREATE TRIGGER (MySQL 5.7 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility SqlSemantics
 */
final class TriggerOrder implements Node
{
    use Snapshot;

    /**
     * @param OrderPlacement $placement Whether the trigger runs after or before the other
     * @param Name $other The name of the other trigger
     */
    public function __construct(public readonly OrderPlacement $placement, public readonly Name $other)
    {
    }

    /**
     * Writes the keyword and the other trigger name.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->placement->value)->name($this->other, NameUse::Identifier);
    }
}
