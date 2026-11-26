<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceRight;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequencePrincipal;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Resolved creator, schema and recipient roles in a future sequence privilege change.
 * @visibility public
 * @example Selecting global defaults for the effective creator
 *     $role = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\SequenceGrantee(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Access\UniversalGrantee::Everyone);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Defaults\SequenceDefaultPolicy([], [], [], [$role]))->scopeSql() // => 'ALTER DEFAULT PRIVILEGES'
 */
final class SequenceDefaultPolicy
{
    use Snapshot;

    /**
     * @var list<SequencePrincipal> Empty selects the effective creator.
     */
    public readonly array $creators;

    /**
     * @var list<Identifier> Empty selects the global default ACL layer.
     */
    public readonly array $schemas;

    /**
     * @var list<SequenceRight> Empty selects all sequence rights.
     */
    public readonly array $rights;

    /**
     * @var non-empty-list<SequenceGrantee>
     */
    public readonly array $grantees;

    /**
     * Checks the effective operands and detaches every supplied list.
     * @param list<SequencePrincipal> $creators
     * @param list<Identifier> $schemas
     * @param list<SequenceRight> $rights
     * @param list<SequenceGrantee> $grantees
     */
    public function __construct(
        array $creators,
        array $schemas,
        array $rights,
        array $grantees,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $owners = Check::listOf($creators, SequencePrincipal::class, 'Default sequence privileges require an ordered list of creator roles.');
        foreach ($owners as $role) {
            $role->checkRelease($release);
        }
        $namespaces = Check::listOf($schemas, Identifier::class, 'Default sequence privileges require an ordered list of schema identities.');
        foreach ($namespaces as $schema) {
            Check::input(
                $schema->value !== 'pg_temp' && !IndexSchemas::sessionInternal($schema->value),
                'Default sequence privileges require named persistent schemas.'
            );
        }
        $recipients = Check::listOf($grantees, SequenceGrantee::class, 'Default sequence privileges require an ordered nonempty list of recipients.', 1);
        foreach ($recipients as $role) {
            $role->checkRelease($release);
        }
        $this->creators = $owners;
        $this->schemas = $namespaces;
        $this->rights = Check::listOf($rights, SequenceRight::class, 'Default sequence privileges require an ordered list of rights.');
        $this->grantees = $recipients;
    }

    /**
     * Identifies the native release and lexical profile selected for the policy.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the exact creator and schema clauses when explicit.
     */
    public function scopeSql(): string
    {
        $owners = [];
        foreach ($this->creators as $role) {
            $owners[] = $role->reference();
        }
        $schemas = [];
        foreach ($this->schemas as $schema) {
            $schemas[] = $schema->quoted();
        }

        return 'ALTER DEFAULT PRIVILEGES' . ($owners === [] ? '' : ' FOR ROLE ' . implode(', ', $owners))
            . ($schemas === [] ? '' : ' IN SCHEMA ' . implode(', ', $schemas));
    }

    /**
     * Emits the checked privileges in the default sequence object role.
     */
    public function objectSql(): string
    {
        $names = [];
        foreach ($this->rights as $right) {
            $names[] = $right->value;
        }

        return ($names === [] ? 'ALL' : implode(', ', $names)) . ' ON SEQUENCES';
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
}
