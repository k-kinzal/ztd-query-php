<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\GrantSequenceAccess;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\NamedSequences;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the grant phase against the sequence and authorization established by its parent.
 *
 * @visibility SqlSemantics
 */
final class SchemaGrantOrder
{
    /**
     * Checks each exact local privilege target in grant order.
     *
     * @param list<CreateSequence> $sequences The preceding sequence phase
     * @param list<GrantSequenceAccess> $grants The compiled grants
     */
    public static function check(?Identifier $name, Identifier|SchemaSessionRole $owner, bool $guard, GrammarRelease $release, array $sequences, array $grants): void
    {
        if ($grants === []) {
            return;
        }
        Check::input($name !== null && !$guard, 'Schema grants require an unguarded, statically named namespace.');
        $local = [];
        foreach ($sequences as $sequence) {
            $local[$sequence->name->name->value] = true;
        }
        foreach ($grants as $grant) {
            $access = $grant->access;
            Check::input($access->release === $release, 'A schema grant must belong to its release.');
            $grantor = $access->grantor?->owner;
            Check::input(
                !$owner instanceof Identifier || !$grantor instanceof Identifier || $owner->value === $grantor->value,
                'A named schema grantor must match its named schema owner.'
            );
            if ($access->target instanceof NamedSequences) {
                foreach ($access->target->sequences as $sequence) {
                    Check::input(
                        $sequence->schema?->value !== $name->value || isset($local[$sequence->name->value]),
                        'A schema grant requires a preceding sequence at each named local target.'
                    );
                }
            } elseif ($local !== [] && $grant->grantOption && $access->hasEffectiveRights()) {
                foreach ($access->target->schemas as $schema) {
                    if ($schema->value === $name->value) {
                        foreach ($access->grantees as $grantee) {
                            Check::input(
                                $grantee->role !== UniversalGrantee::Everyone,
                                'A populated schema grant requires individual grantees for grant options.'
                            );
                        }
                    }
                }
            }
        }
    }
}
