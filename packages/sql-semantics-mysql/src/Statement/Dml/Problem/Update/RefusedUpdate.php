<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Update;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone update whose input columns require a retained instruction role.
 *
 * @visibility public
 * @example Refusing duplicate input columns before publishing a statement
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1") // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedUpdate implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredUpdate $write The complete instruction and its dependencies
     */
    public function __construct(public readonly DeferredUpdate $write)
    {
    }

    /**
     * Describes the fixed contradiction without selecting a runtime error order.
     */
    public function message(): string
    {
        $problem = (new \SqlSemantics\Platform\MySql\Rules\Query\From\JsonColumns())->of($this->write);
        \SqlSemantics\Diagnostic\Check::invariant($problem !== null, 'A retained UPDATE has a fixed input-column constraint.');
        return $problem->message();
    }
}
