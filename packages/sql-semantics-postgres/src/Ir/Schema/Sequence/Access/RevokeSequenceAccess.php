<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Revokes resolved native privileges or grant options on sequences.
 * @visibility public
 * @example Analyzing a schema-wide revoke that cascades
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('REVOKE USAGE ON ALL SEQUENCES IN SCHEMA public FROM PUBLIC CASCADE')->toString() // => 'REVOKE USAGE ON ALL SEQUENCES IN SCHEMA "public" FROM PUBLIC CASCADE'
 */
final class RevokeSequenceAccess implements SemanticsIR
{
    use Snapshot;

    /**
     * Chooses the exact revocation and dependent grant policy.
     */
    public function __construct(
        public readonly SequenceAccess $access,
        public readonly bool $grantOptionOnly = false,
        public readonly SequenceGrantDependency $dependency = SequenceGrantDependency::Restrict,
    ) {
    }

    /**
     * Identifies the exact native profile of this statement.
     */
    public function profileId(): string
    {
        return $this->access->profileId();
    }

    /**
     * Emits the checked revoke and its dependent grant policy.
     */
    public function toString(): string
    {
        return 'REVOKE ' . ($this->grantOptionOnly ? 'GRANT OPTION FOR ' : '') . $this->access->objectSql()
            . ' FROM ' . $this->access->recipientsSql() . $this->access->grantorSql() . ' ' . $this->dependency->value;
    }
}
