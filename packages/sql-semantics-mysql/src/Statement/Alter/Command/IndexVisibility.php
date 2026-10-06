<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `ALTER INDEX i VISIBLE | INVISIBLE` (8.0 and later): a request to make an index visible to or hidden from the optimizer.
 *
 * Mirrors PT_alter_table_index_visible.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/invisible-indexes.html.
 *
 * @visibility public
 * @example Hiding an index
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t ALTER INDEX i INVISIBLE');
 *     $alter->statement->commands[0]->visible // => false
 */
final class IndexVisibility implements AlterCommand
{
    use Snapshot;

    /**
     * @param Name $index The index name
     * @param bool $visible Whether VISIBLE (true) or INVISIBLE (false) is written
     */
    public function __construct(public readonly Name $index, public readonly bool $visible)
    {
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'INDEX')->name($this->index, NameUse::Label)->keyword($this->visible ? 'VISIBLE' : 'INVISIBLE');
    }
}
