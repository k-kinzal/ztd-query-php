<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;

/**
 * `ALTER INDEX i VISIBLE | INVISIBLE` (8.0 and later): a request to make an index visible to or hidden from the optimizer.
 *
 * Mirrors PT_alter_table_index_visible.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/invisible-indexes.html.
 *
 * @visibility SqlSemantics
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
