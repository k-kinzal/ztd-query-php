<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A table that DROP TABLE names more than once.
 *
 * The server rejects the statement with ER_NONUNIQ_TABLE.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html.
 *
 * @visibility public
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Alter\Problem\RepeatedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))))->message() // => 'Table t is named more than once.'
 */
final class RepeatedTable implements Diagnostic
{
    use Snapshot;

    /**
     * @param QualifiedName $name The repeated name as written at its later occurrence
     */
    public function __construct(public readonly QualifiedName $name)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return 'Table ' . ($this->name->schema === null ? '' : $this->name->schema->value . '.') . $this->name->name->value . ' is named more than once.';
    }
}
