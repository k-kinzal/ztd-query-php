<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\GroupCredentials;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An intrinsic group credential condition with the complete retained options.
 *
 * @visibility public
 * @example Describing a password without a user
 *     $request = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart([new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('p'))]);
 *     $request->problem()->condition()->error === \SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError::GroupUserMissing // => true
 */
final class RefusedGroupStart implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredGroupStart $request The complete retained request
     */
    public function __construct(public readonly DeferredGroupStart $request)
    {
    }

    /**
     * Returns the credential condition independently of replication state.
     */
    public function condition(): RefusedSetting
    {
        $problem = (new GroupCredentials())->problem($this->request->options);
        Check::invariant($problem !== null, 'A deferred group start retains an intrinsic credential condition.');
        return $problem;
    }

    /**
     * Describes the retained condition.
     */
    public function message(): string
    {
        return $this->condition()->message();
    }
}
