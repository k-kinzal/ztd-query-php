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
 * One table a DROP TABLE removes: the position its resolution is recorded at.
 *
 * The statement that holds it records the resolution of the name as the
 * relation fact of this node.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 *
 * @visibility SqlSemantics
 * @example Holding a table name with its database
 *     $target = new \SqlSemantics\Platform\MySql\Statement\Alter\TargetTable(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t'), new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('shop')));
 *     $target->name->schema?->value // => 'shop'
 */
final class TargetTable implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name with its optional database
     */
    public function __construct(public readonly QualifiedName $name)
    {
        Check::input($name->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
    }
}
