<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One event a trigger fires on; UPDATE may name columns.
 *
 * Mirrors one bit of `events` with `columns` for UPDATE OF.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility public
 * @example Reading an UPDATE OF event
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRIGGER g AFTER UPDATE OF a, b ON t EXECUTE FUNCTION f()');
 *     count($statement->statement->events[0]->columns) // => 2
 */
final class TriggerEvent implements Node
{
    use Snapshot;

    /**
     * @var list<Name> The columns of UPDATE OF
     */
    public readonly array $columns;

    /**
     * @param TriggerEventKind $kind The event
     * @param list<Name> $columns The columns of UPDATE OF
     */
    public function __construct(public readonly TriggerEventKind $kind, array $columns = [])
    {
        $this->columns = Check::listOf($columns, Name::class, 'UPDATE OF names columns.');
        Check::input($this->columns === [] || $kind === TriggerEventKind::Update, 'Only UPDATE names columns.');
    }

    /**
     * Writes the event.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->columns !== []) {
            $out->keyword('OF');
            (new Writing())->names($out, $this->columns);
        }
    }
}
