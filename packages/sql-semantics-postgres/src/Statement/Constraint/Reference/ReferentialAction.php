<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;

/**
 * One ON UPDATE or ON DELETE clause of a foreign key.
 *
 * Mirrors `fk_upd_action`/`fk_del_action` with `fk_del_set_cols`: SET NULL
 * and SET DEFAULT may name the columns they set. The server accepts such a
 * column list only for ON DELETE, which is a diagnostic of the constraint.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility SqlSemantics
 * @example Refusing a column list for an action that sets nothing
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::Cascade, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')]) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class ReferentialAction implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The columns SET NULL or SET DEFAULT sets; none means all referencing columns
     */
    public readonly array $columns;

    /**
     * @param ReferenceEvent $event The change answered
     * @param ReferenceAction $action The action taken
     * @param list<Name> $columns The columns SET NULL or SET DEFAULT sets
     */
    public function __construct(public readonly ReferenceEvent $event, public readonly ReferenceAction $action, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'The columns of a referential action are names.');
        Check::input($this->columns === [] || $action->setsColumns(), 'Only SET NULL and SET DEFAULT name columns.');
    }

    /**
     * Writes ON, the event, the action and its columns.
     */
    public function render(Output $out): void
    {
        $out->keyword('ON', $this->event->value, ...explode(' ', $this->action->value));
        if ($this->columns !== []) {
            (new Writing())->parenthesized($out, $this->columns);
        }
    }
}
