<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * One column of a table definition.
 *
 * @visibility public
 * @example Reading a column definition
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER NOT NULL)');
 *     [$create->statement->columns[0]->name->value, $create->statement->columns[0]->notNull] // => ['a', true]
 */
final class ColumnDefinition implements Node
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param Name|null $domain The declared type name
     * @param bool $notNull Whether the column forbids NULL
     */
    public function __construct(public readonly Name $name, public readonly ?Name $domain = null, public readonly bool $notNull = false)
    {
    }

    /**
     * Writes the name, the declared type and the NULL constraint.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column);
        if ($this->domain !== null) {
            $out->name($this->domain, NameUse::Label);
        }
        if ($this->notNull) {
            $out->keyword('NOT', 'NULL');
        }
    }
}
