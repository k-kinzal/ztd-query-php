<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * An ON DELETE, ON UPDATE or ON INSERT clause of a foreign key.
 *
 * Rule: SQLITE-FK-ACTION-001. The reaction applies to the child rows when the
 * parent key is deleted or updated. An ON INSERT clause is grammatical and
 * has no effect.
 * Source: https://sqlite.org/foreignkeys.html#fk_actions. Status: Implemented.
 *
 * @visibility public
 * @example Reading a foreign key action
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent ON DELETE NO ACTION)');
 *     $action = $create->statement->columns[0]->constraints[0]->arguments[0];
 *     [$action->event->value, $action->reaction->value] // => ['DELETE', 'NO ACTION']
 */
final class ReferenceAction implements ReferenceArgument
{
    use Snapshot;

    /**
     * @param ReferenceEvent $event The change of the parent key
     * @param ReferenceReaction $reaction What happens to the child rows
     */
    public function __construct(public readonly ReferenceEvent $event, public readonly ReferenceReaction $reaction)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', $this->event->value, ...explode(' ', $this->reaction->value));
    }
}
