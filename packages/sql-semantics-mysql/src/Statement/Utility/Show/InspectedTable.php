<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Node;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The table or view a SHOW or DESCRIBE reports on.
 *
 * The node is the table use: its resolution is the relation fact the
 * operation records for it (MYSQL-SHOW-TARGET-001).
 *
 * @visibility SqlSemantics
 */
final class InspectedTable implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name with its optional database
     */
    public function __construct(public readonly QualifiedName $name)
    {
        Check::input($name->catalog === null, 'A table name has at most a database qualifier.');
    }

    /**
     * Writes the database and the table name.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
    }
}
