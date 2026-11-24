<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Resolved operands shared by sequence privilege changes.
 * @visibility public
 * @example Requesting usage privileges for the current user
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequencesInSchemas([new $name('public')]);
 *     $role = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\SchemaSessionRole::Effective);
 *     $recipient = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee($role);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceAccess($target, [\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight::Usage], [$recipient]))->objectSql() // => 'USAGE ON ALL SEQUENCES IN SCHEMA "public"'
 */
final class SequenceAccess
{
    use Snapshot;

    /**
     * @var list<SequenceRight>
     */
    public readonly array $rights;

    /**
     * @var non-empty-list<SequenceGrantee>
     */
    public readonly array $grantees;

    /**
     * Checks the exact release and detaches both role lists.
     * @param list<SequenceRight> $rights Empty selects all rights.
     * @param list<SequenceGrantee> $grantees
     */
    public function __construct(
        public readonly NamedSequences|SequencesInSchemas $target,
        array $rights,
        array $grantees,
        public readonly ?SequencePrincipal $grantor = null,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        if ($target instanceof NamedSequences) {
            $target->checkRelease($release);
        }
        $grantor?->checkRelease($release);
        $this->rights = Check::listOf($rights, SequenceRight::class, 'Sequence privileges require an ordered list of rights.');
        $recipients = Check::listOf($grantees, SequenceGrantee::class, 'Sequence privileges require an ordered nonempty list of recipients.', 1);
        foreach ($recipients as $recipient) {
            $recipient->checkRelease($release);
        }
        $this->grantees = $recipients;
    }

    /**
     * Identifies the exact native release and lexical settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Reports whether the request includes any effective rights.
     */
    public function hasEffectiveRights(): bool
    {
        if ($this->rights === []) {
            return true;
        }
        foreach ($this->rights as $right) {
            if ($right !== SequenceRight::LegacyRule) {
                return true;
            }
        }

        return false;
    }

    /**
     * Emits the checked privilege names and resolved target operation.
     */
    public function objectSql(): string
    {
        $names = [];
        foreach ($this->rights as $right) {
            $names[] = $right->value;
        }
        $rights = $names === [] ? 'ALL' : implode(', ', $names);

        return $rights . ' ON ' . $this->target->toString();
    }

    /**
     * Emits the ordered recipient roles.
     */
    public function recipientsSql(): string
    {
        $roles = [];
        foreach ($this->grantees as $role) {
            $roles[] = $role->toString();
        }

        return implode(', ', $roles);
    }

    /**
     * Emits the grantor clause when a principal has been specified.
     */
    public function grantorSql(): string
    {
        return $this->grantor === null ? '' : ' GRANTED BY ' . $this->grantor->reference();
    }
}
