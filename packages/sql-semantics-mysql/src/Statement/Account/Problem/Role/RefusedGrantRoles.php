<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\Role;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredGrantRoles;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete role grant outside the ordinary GRANT role domain.
 *
 * @visibility public
 * @example Describing a contradictory role grant
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() GRANT '' TO u")->statement;
 *     $create->body->problem()->message() // => 'GRANT cannot use anonymous accounts or grant an explicit account to itself.'
 */
final class RefusedGrantRoles implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredGrantRoles $request The complete retained definition
     */
    public function __construct(public readonly DeferredGrantRoles $request)
    {
    }

    /**
     * Describes the fixed condition independently of account catalog state.
     */
    public function message(): string
    {
        return 'GRANT cannot use anonymous accounts or grant an explicit account to itself.';
    }
}
