<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The table or view a SHOW or DESCRIBE reports on.
 *
 * The node is the table use: its resolution is the relation fact the
 * operation records for it (MYSQL-SHOW-TARGET-001).
 *
 * @visibility public
 * @example Resolving the inspected table
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT)');
 *     $show = $semantics->analyze('SHOW CREATE TABLE t', [$table]);
 *     $show->facts->relation($show->statement->table)->table->table === $table->declarations()[0] // => true
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
