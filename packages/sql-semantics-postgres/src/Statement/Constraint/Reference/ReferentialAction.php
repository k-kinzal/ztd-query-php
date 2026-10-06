<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One ON UPDATE or ON DELETE clause of a foreign key.
 *
 * Mirrors `fk_upd_action`/`fk_del_action` with `fk_del_set_cols`: SET NULL
 * and SET DEFAULT may name the columns they set. The server accepts such a
 * column list only for ON DELETE, which is a diagnostic of the constraint.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html#SQL-CREATETABLE-PARMS-REFERENCES.
 *
 * @visibility public
 * @example Reading the columns SET NULL sets
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, b int, FOREIGN KEY (a, b) REFERENCES u ON DELETE SET NULL (b))');
 *     $create->statement->definition->elements[2]->actions[0]->columns[0]->value // => 'b'
 * @example Refusing a column list for an action that sets nothing
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::Cascade, [new \SqlSemantics\Statement\Identifier\Name('a')]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
