<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One ON UPDATE or ON DELETE clause of a foreign key reference.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html#foreign-key-referential-actions.
 *
 * @visibility public
 * @example Reading a referential action
 *     $action = new \SqlSemantics\Platform\MySql\Statement\Table\Key\ReferentialAction(\SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceEvent::Delete, \SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption::Cascade);
 *     [$action->event->value, $action->option->value] // => ['DELETE', 'CASCADE']
 */
final class ReferentialAction implements Node
{
    use Snapshot;

    /**
     * @param ReferenceEvent $event The change of the parent row
     * @param ReferenceOption $option What happens to the child rows
     */
    public function __construct(public readonly ReferenceEvent $event, public readonly ReferenceOption $option)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', $this->event->value, ...explode(' ', $this->option->value));
    }
}
