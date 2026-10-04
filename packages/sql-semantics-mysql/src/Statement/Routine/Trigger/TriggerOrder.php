<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Trigger;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The FOLLOWS or PRECEDES clause of CREATE TRIGGER (MySQL 5.7 and later).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-trigger.html.
 *
 * @visibility public
 * @example Reading the order of a trigger
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW PRECEDES other SET @a = 1');
 *     [$create->statement->order->placement->value, $create->statement->order->other->value] // => ['PRECEDES', 'other']
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
