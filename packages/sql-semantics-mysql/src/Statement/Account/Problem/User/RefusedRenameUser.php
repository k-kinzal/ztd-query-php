<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\Problem\User;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\Renamings;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete rename list outside the ordinary account-identity domain.
 *
 * @visibility public
 * @example Describing the retained identity contradiction
 *     $account = new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser();
 *     $request = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRenameUser([new \SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming($account, new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
 *     $request->problem()->message() // => 'RENAME USER requires consistent source and destination identities throughout its ordered pairs.'
 */
final class RefusedRenameUser implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredRenameUser $request The complete ordered rename list
     */
    public function __construct(public readonly DeferredRenameUser $request)
    {
    }

    /**
     * Describes a fixed identity contradiction without selecting catalog-dependent errors.
     */
    public function message(): string
    {
        $problem = (new Renamings())->problem($this->request->renamings);
        Check::invariant($problem !== null, 'A deferred account rename retains contradictory identity requirements.');
        return $problem;
    }
}
