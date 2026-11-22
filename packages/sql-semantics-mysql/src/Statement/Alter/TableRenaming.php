<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * `old TO new`: one rename of RENAME TABLE.
 *
 * The statement records the resolution of the old name, at the time the
 * rename runs, as the relation fact of this node.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-table.html.
 *
 * @visibility SqlSemantics
 * @example Moving a table to another database
 *     $renaming = new \SqlSemantics\Platform\MySql\Statement\Alter\TableRenaming(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t')), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t'), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('archive')));
 *     $renaming->to->schema?->value // => 'archive'
 */
final class TableRenaming implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $from The current name with its optional database
     * @param QualifiedName $to The new name with its optional database
     */
    public function __construct(public readonly QualifiedName $from, public readonly QualifiedName $to)
    {
        Check::input($from->catalog === null && $to->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Writes the rename.
     */
    public function render(Output $out): void
    {
        foreach ([$this->from, $this->to] as $index => $name) {
            if ($index > 0) {
                $out->keyword('TO');
            }
            if ($name->schema !== null) {
                $out->name($name->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($name->name, NameUse::Relation);
        }
    }
}
