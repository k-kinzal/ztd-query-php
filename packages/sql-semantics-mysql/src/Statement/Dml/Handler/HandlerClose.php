<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * HANDLER ... CLOSE: closes an open handler.
 *
 * Rule: MYSQL-HANDLER-CLOSE-001. The handler is session state; the
 * statement derives nothing and returns no rows. Terminates: no child.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class HandlerClose implements Statement
{
    use Snapshot;

    /**
     * @param Name $handler The name of the handler
     */
    public function __construct(public readonly Name $handler)
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
        $out->keyword('HANDLER')->name($this->handler, NameUse::Relation)->keyword('CLOSE');
    }
}
