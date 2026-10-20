<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\Role;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateRole;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete role definition outside the ordinary CREATE ROLE domain.
 *
 * @visibility public
 * @example Describing an anonymous role definition
 *     $request = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateRole(false, [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name(''))]);
 *     $request->problem()->message() // => 'CREATE ROLE cannot define an anonymous account.'
 */
final class RefusedCreateRole implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredCreateRole $request The complete retained definition
     */
    public function __construct(public readonly DeferredCreateRole $request)
    {
    }

    /**
     * Describes the fixed condition independently of account catalog state.
     */
    public function message(): string
    {
        return 'CREATE ROLE cannot define an anonymous account.';
    }
}
