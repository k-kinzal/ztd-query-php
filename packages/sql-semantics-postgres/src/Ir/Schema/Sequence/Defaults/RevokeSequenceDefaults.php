<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantDependency;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Removes privileges or grant options from future sequence defaults.
 * @visibility public
 * @example Revoking a sequence default in a known namespace
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT ON SEQUENCES FROM PUBLIC')->toString() // => 'ALTER DEFAULT PRIVILEGES IN SCHEMA "public" REVOKE SELECT ON SEQUENCES FROM PUBLIC RESTRICT'
 */
final class RevokeSequenceDefaults implements SemanticsIR
{
    use Snapshot;

    /**
     * Selects the exact option-only and dependency behavior.
     */
    public function __construct(
        public readonly SequenceDefaultPolicy $policy,
        public readonly bool $grantOptionOnly = false,
        public readonly SequenceGrantDependency $dependency = SequenceGrantDependency::Restrict,
    ) {
    }

    /**
     * Identifies the exact native profile of the policy change.
     */
    public function profileId(): string
    {
        return $this->policy->profileId();
    }

    /**
     * Emits the native default revoke.
     */
    public function toString(): string
    {
        return $this->policy->scopeSql() . ' REVOKE ' . ($this->grantOptionOnly ? 'GRANT OPTION FOR ' : '')
            . $this->policy->objectSql() . ' FROM ' . $this->policy->recipientsSql() . ' ' . $this->dependency->value;
    }
}
