<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\StockRoles;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Names one owning principal of a native sequence.
 * @visibility public
 * @example Assigning the authenticated connection role
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::Session))->toString() // => 'OWNER TO SESSION_USER'
 */
final class SequencePrincipal
{
    use Snapshot;

    /**
     * Requires a single decoded role or a closed session role.
     */
    public function __construct(public readonly Identifier|SchemaSessionRole $owner)
    {
        Check::input(
            !$owner instanceof Identifier || ($owner->value !== 'public' && $owner->value !== 'none'),
            'A sequence owner must identify a single role.'
        );
    }

    /**
     * Checks that a reserved stock role exists in the selected release.
     */
    public function checkRelease(GrammarRelease $release): void
    {
        $owner = $this->owner;
        Check::input(
            !$owner instanceof Identifier || !str_starts_with($owner->value, 'pg_') || StockRoles::contains($release, $owner->value),
            'The stock sequence owner must exist in this release.'
        );
    }

    /**
     * Emits the resolved nominal role or the native session-role keyword.
     */
    public function toString(): string
    {
        $name = $this->owner instanceof Identifier ? $this->owner->quoted() : $this->owner->value;

        return 'OWNER TO ' . $name;
    }
}
