<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation\Hint;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One index hint of a table reference: `USE|FORCE|IGNORE INDEX [FOR scope] (indexes)`.
 *
 * USE accepts an empty list, which asks for no index; FORCE and IGNORE name
 * at least one index. The names are not checked against declarations: the
 * analysis context holds no indexes. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/index-hints.html.
 *
 * @visibility public
 * @example Reading an index hint
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM t IGNORE KEY FOR GROUP BY (i, j)');
 *     [$query->statement->from->indexHints[0]->action, count($query->statement->from->indexHints[0]->indexes)] // => [\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction::Ignore, 2]
 * @example Refusing FORCE INDEX without an index
 *     new \SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHint(\SqlSemantics\Platform\MySql\Statement\Relation\Hint\IndexHintAction::Force, null, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class IndexHint implements Node
{
    use Snapshot;

    /**
     * @var list<Name|PrimaryIndex> The indexes in written order
     */
    public readonly array $indexes;

    /**
     * @param IndexHintAction $action What the hint asks
     * @param IndexHintScope|null $scope The part of processing it applies to
     * @param list<Name|Node> $indexes The indexes in written order, each a name or the primary key
     */
    public function __construct(public readonly IndexHintAction $action, public readonly ?IndexHintScope $scope, array $indexes)
    {
        $list = [];
        foreach ($indexes as $index) {
            Check::input($index instanceof Name || $index instanceof PrimaryIndex, 'An index is named or is the primary key.');
            $list[] = $index;
        }
        $this->indexes = $list;
        Check::input($action === IndexHintAction::Use || $list !== [], 'FORCE INDEX and IGNORE INDEX name at least one index.');
    }

    /**
     * Writes the hint.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->action->value, 'INDEX');
        if ($this->scope !== null) {
            $out->keyword(...match ($this->scope) {
                IndexHintScope::Join => ['FOR', 'JOIN'],
                IndexHintScope::OrderBy => ['FOR', 'ORDER', 'BY'],
                IndexHintScope::GroupBy => ['FOR', 'GROUP', 'BY'],
            });
        }
        $out->symbol('(');
        foreach ($this->indexes as $position => $index) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($index instanceof Name) {
                $out->name($index, NameUse::Alias);
            } else {
                $out->node($index);
            }
        }
        $out->symbol(')');
    }
}
