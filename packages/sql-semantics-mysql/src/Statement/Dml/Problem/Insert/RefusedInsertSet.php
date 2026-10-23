<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone SET write refused without discarding its destination or siblings.
 *
 * @visibility public
 * @example Keeping the rejected write outside the ordinary statement domain
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SET a=ROW(1,2)');
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Problem\Insert\RefusedInsertSet($operation->statement->body))->message() // => 'Operand should contain 1 column(s), not 2.'
 */
final class RefusedInsertSet implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredInsertSet $write The complete write preparation dependencies
     */
    public function __construct(public readonly DeferredInsertSet $write)
    {
    }

    /**
     * Describes the retained operand condition.
     */
    public function message(): string
    {
        return $this->write->problem()->message();
    }
}
