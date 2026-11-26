<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Grants initial privileges for future sequences.
 * @visibility public
 * @example Granting usage on sequences created in a known namespace
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE ON SEQUENCES TO PUBLIC')->toString() // => 'ALTER DEFAULT PRIVILEGES IN SCHEMA "public" GRANT USAGE ON SEQUENCES TO PUBLIC'
 */
final class GrantSequenceDefaults implements SemanticsIR
{
    use Snapshot;

    /**
     * Requires individual grantees when granting the ability to grant.
     */
    public function __construct(public readonly SequenceDefaultPolicy $policy, public readonly bool $grantOption = false)
    {
        if ($grantOption) {
            foreach ($policy->grantees as $grantee) {
                Check::input($grantee->role !== UniversalGrantee::Everyone, 'Default sequence grant options require individual recipients.');
            }
        }
    }

    /**
     * Identifies the exact native profile of the policy change.
     */
    public function profileId(): string
    {
        return $this->policy->profileId();
    }

    /**
     * Emits the native default grant.
     */
    public function toString(): string
    {
        return $this->policy->scopeSql() . ' GRANT ' . $this->policy->objectSql() . ' TO ' . $this->policy->recipientsSql()
            . ($this->grantOption ? ' WITH GRANT OPTION' : '');
    }
}
