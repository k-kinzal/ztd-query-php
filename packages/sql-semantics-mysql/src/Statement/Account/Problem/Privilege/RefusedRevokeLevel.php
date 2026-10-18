<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\Privilege;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A refused routine revocation, preserving every target and account operand.
 *
 * @visibility public
 * @example Describing a stored target condition
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE ALL ON PROCEDURE * FROM u');
 *     $operation->statement->body->problem()->message() // => 'The routine revocation does not name a routine object.'
 */
final class RefusedRevokeLevel implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredRevokePrivileges $request The complete retained instruction
     */
    public function __construct(public readonly DeferredRevokePrivileges $request)
    {
    }

    /**
     * Describes the target condition independently of current catalog state.
     */
    public function message(): string
    {
        return 'The routine revocation does not name a routine object.';
    }
}
