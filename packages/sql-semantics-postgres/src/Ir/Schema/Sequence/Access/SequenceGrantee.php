<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One privilege recipient, either a single principal or the universal grantee.
 * @visibility public
 * @example Naming the universal grantee
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee::Everyone))->toString() // => 'PUBLIC'
 */
final class SequenceGrantee
{
    use Snapshot;

    /**
     * Selects one exact recipient role.
     */
    public function __construct(public readonly SequencePrincipal|UniversalGrantee $role)
    {
    }

    /**
     * Checks release-specific availability of nominal roles.
     */
    public function checkRelease(GrammarRelease $release): void
    {
        if ($this->role instanceof SequencePrincipal) {
            $this->role->checkRelease($release);
        }
    }

    /**
     * Quotes the nominal role or emits the closed authorization keyword.
     */
    public function toString(): string
    {
        return $this->role instanceof SequencePrincipal ? $this->role->reference() : $this->role->value;
    }
}
