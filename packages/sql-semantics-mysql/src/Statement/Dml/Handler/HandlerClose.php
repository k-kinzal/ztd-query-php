<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Handler;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * HANDLER ... CLOSE: closes an open handler.
 *
 * Rule: MYSQL-HANDLER-CLOSE-001. The handler is session state; the
 * statement derives nothing and returns no rows. Terminates: no child.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a HANDLER ... CLOSE
 *     $close = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('HANDLER h CLOSE');
 *     [$close->statement->handler->value, $close->toString()] // => ['h', 'HANDLER h CLOSE']
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
