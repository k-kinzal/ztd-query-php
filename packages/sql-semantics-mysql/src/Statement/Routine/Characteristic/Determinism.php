<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Characteristic;

use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * The DETERMINISTIC or NOT DETERMINISTIC characteristic of a stored routine.
 *
 * It is accepted by CREATE only; ALTER PROCEDURE and ALTER FUNCTION have no
 * such characteristic in the grammar.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html.
 *
 * @visibility public
 * @example Reading whether a function is declared deterministic
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE FUNCTION f() RETURNS INT NOT DETERMINISTIC RETURN 1');
 *     $create->statement->characteristics[0]->deterministic // => false
 */
final class Determinism implements Characteristic
{
    use Snapshot;

    /**
     * @param bool $deterministic Whether the routine is declared to return the same result for the same input
     */
    public function __construct(public readonly bool $deterministic)
    {
    }

    /**
     * Writes DETERMINISTIC, after NOT when the routine is declared not deterministic.
     */
    public function render(Output $out): void
    {
        if (!$this->deterministic) {
            $out->keyword('NOT');
        }
        $out->keyword('DETERMINISTIC');
    }
}
