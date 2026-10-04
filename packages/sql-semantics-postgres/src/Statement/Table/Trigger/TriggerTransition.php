<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One REFERENCING item: a name for the old or new transition table.
 *
 * Mirrors PostgreSQL's `TriggerTransition`. The optional AS is not kept. ROW transition variables are not
 * supported by the server, which is reported. Transition tables are visible to the trigger function only.
 * Source: https://www.postgresql.org/docs/17/sql-createtrigger.html.
 *
 * @visibility public
 * @example Naming a transition table
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TRIGGER g AFTER INSERT ON t REFERENCING NEW TABLE AS fresh FOR EACH STATEMENT EXECUTE FUNCTION f()');
 *     $statement->statement->transitions[0]->name->value // => 'fresh'
 */
final class TriggerTransition implements Node
{
    use Snapshot;

    /**
     * @param bool $new Whether the item names the new rows; the old ones otherwise
     * @param bool $table Whether TABLE is written; ROW otherwise
     * @param Name $name The name
     */
    public function __construct(public readonly bool $new, public readonly bool $table, public readonly Name $name)
    {
    }

    /**
     * Writes the item.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->new ? 'NEW' : 'OLD', $this->table ? 'TABLE' : 'ROW')->name($this->name);
    }
}
