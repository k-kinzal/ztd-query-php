<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * DEALLOCATE PREPARE (or its synonym DROP PREPARE): releases a prepared statement.
 *
 * Rule: MYSQL-DEALLOCATE-001. The prepared statement is a session object;
 * the statement derives nothing and returns no rows. Terminates: no child.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/deallocate-prepare.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Deallocate implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The name of the prepared statement
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives nothing: the statement holds no expression and returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEALLOCATE', 'PREPARE')->name($this->name, NameUse::Label);
    }
}
