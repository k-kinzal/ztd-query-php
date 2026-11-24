<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Grants resolved privileges on native PostgreSQL sequences.
 * @visibility public
 * @example Analyzing a schema-wide sequence grant
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $semantics->analyze('GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO PUBLIC')->toString() // => 'GRANT USAGE ON ALL SEQUENCES IN SCHEMA "public" TO PUBLIC'
 */
final class GrantSequenceAccess implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the conditions for giving a recipient the ability to grant privileges.
     */
    public function __construct(public readonly SequenceAccess $access, public readonly bool $grantOption = false)
    {
        if ($grantOption && $access->target instanceof NamedSequences && $access->hasEffectiveRights()) {
            foreach ($access->grantees as $recipient) {
                Check::input($recipient->role !== UniversalGrantee::Everyone, 'Grant options on named sequences require individual grantees.');
            }
        }
    }

    /**
     * Identifies the exact native profile of this statement.
     */
    public function profileId(): string
    {
        return $this->access->profileId();
    }

    /**
     * Emits the checked grant and its native authorization roles.
     */
    public function toString(): string
    {
        return 'GRANT ' . $this->access->objectSql() . ' TO ' . $this->access->recipientsSql()
            . ($this->grantOption ? ' WITH GRANT OPTION' : '') . $this->access->grantorSql();
    }
}
