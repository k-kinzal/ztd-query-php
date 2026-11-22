<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * Whether a name denotes a base table or a view, which decides the columns SHOW CREATE TABLE returns.
 *
 * A declaration states whether a relation is a base table or a view; for a
 * name the context does not declare, the columns depend on that fact.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 *
 * @visibility SqlSemantics
 * @example Describing the missing fact
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t'))))->describe() // => 'whether t is a base table or a view'
 */
final class TableOrView implements MissingInput
{
    use Snapshot;

    /**
     * @param QualifiedName $name The name as the statement wrote it
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the missing fact.
     */
    public function describe(): string
    {
        return 'whether ' . ($this->name->schema === null ? '' : $this->name->schema->value . '.') . $this->name->name->value . ' is a base table or a view';
    }
}
