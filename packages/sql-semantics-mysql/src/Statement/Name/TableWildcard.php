<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * Every column of one table occurrence: `t.*` or `db.t.*`.
 *
 * The node is a request to expand the columns of the occurrence the
 * qualifier names. The statement that contains it derives the expansion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Holding a qualified star
 *     $star = new \SqlSemantics\Platform\MySql\Statement\Name\TableWildcard(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('shop')));
 *     [$star->table->schema?->value, $star->table->name->value] // => ['shop', 't']
 */
final class TableWildcard implements Node
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table occurrence, and its database when written
     */
    public function __construct(public readonly QualifiedName $table)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Writes the qualifier and the star.
     */
    public function render(Output $out): void
    {
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Qualifier)->symbol('.')->symbol('*');
    }
}
