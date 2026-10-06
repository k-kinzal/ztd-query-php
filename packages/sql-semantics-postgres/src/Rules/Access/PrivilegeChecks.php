<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem\AccessProblemRule;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;

/**
 * Checks the privileges of a GRANT or REVOKE against the kind of object.
 *
 * Rule: PG-PRIVILEGE-CHECK-001. The server knows a fixed list of privilege
 * names (MAINTAIN from release 17; `rule` is accepted and ignored) and a
 * fixed set of them for each kind of object (the table of privileges in the
 * manual). A name outside the list is reported as unrecognized, a known
 * privilege outside the set of the kind as invalid for that kind. A
 * relation named in GRANT may be a sequence, so there the sequence
 * privileges are valid too; for default privileges only the privileges of
 * the kind itself are. A column list is valid on relations only, limits the
 * privilege to SELECT, INSERT, UPDATE or REFERENCES, and cannot be written
 * for default privileges. The right to grant cannot be given to PUBLIC.
 * The names are compared as decoded, as the server does. Termination: one
 * pass over a finite list.
 * Source: https://www.postgresql.org/docs/17/ddl-priv.html#PRIVILEGES-SUMMARY-TABLE, https://www.postgresql.org/docs/16/ddl-priv.html,
 * https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class PrivilegeChecks
{
    /**
     * The privilege names the server knows in every supported release.
     */
    private const KNOWN = ['insert', 'select', 'update', 'delete', 'truncate', 'references', 'trigger', 'execute', 'usage', 'create', 'temp', 'temporary', 'connect', 'set', 'alter system', 'rule'];

    /**
     * The privileges that apply to each kind of object, by the name of the kind.
     */
    private const RIGHTS = [
        'Relation' => ['insert', 'select', 'update', 'delete', 'truncate', 'references', 'trigger', 'maintain'],
        'Sequence' => ['usage', 'select', 'update'],
        'Database' => ['create', 'temp', 'temporary', 'connect'],
        'Domain' => ['usage'],
        'Function' => ['execute'],
        'Procedure' => ['execute'],
        'Routine' => ['execute'],
        'Language' => ['usage'],
        'LargeObject' => ['select', 'update'],
        'Parameter' => ['set', 'alter system'],
        'Schema' => ['usage', 'create'],
        'Tablespace' => ['create'],
        'Type' => ['usage'],
        'ForeignDataWrapper' => ['usage'],
        'ForeignServer' => ['usage'],
    ];

    /**
     * The privileges that can be limited to columns.
     */
    private const COLUMN = ['select', 'insert', 'update', 'references'];

    /**
     * Reports the privileges that are unknown, do not apply to the kind of object, or carry a column list where none is valid.
     *
     * @param list<Privilege> $privileges
     * @param bool $defaults Whether the privileges are default privileges
     */
    public function privileges(Derivation $derivation, array $privileges, PrivilegeObjectKind $object, bool $defaults): void
    {
        $rights = self::RIGHTS[$object->name];
        if ($object === PrivilegeObjectKind::Relation && !$defaults) {
            $rights = [...$rights, ...self::RIGHTS['Sequence']];
        }
        foreach ($privileges as $privilege) {
            if ($privilege->columns !== []) {
                $this->columns($derivation, $privilege, $object, $defaults);
                continue;
            }
            $this->named($derivation, $privilege->privilege(), $rights, $object->subject());
        }
    }

    /**
     * Reports the problems of a privilege that carries a column list.
     */
    public function columns(Derivation $derivation, Privilege $privilege, PrivilegeObjectKind $object, bool $defaults): void
    {
        if ($defaults) {
            $derivation->report(new AccessProblem(AccessProblemRule::DefaultColumns));

            return;
        }
        if ($object !== PrivilegeObjectKind::Relation) {
            $derivation->report(new AccessProblem(AccessProblemRule::ColumnPrivilegesTarget));

            return;
        }
        $this->named($derivation, $privilege->privilege(), self::COLUMN, 'column');
    }

    /**
     * Reports a privilege name that is unknown or outside the given set; ALL, which has no name, is in every set.
     *
     * @param list<string> $rights The privilege names that apply
     * @param string $subject The words the server names the kind with
     */
    public function named(Derivation $derivation, ?string $name, array $rights, string $subject): void
    {
        if ($name === null || $name === 'rule') {
            return;
        }
        if (!$this->known($derivation, $name)) {
            $derivation->report(new AccessProblem(AccessProblemRule::UnrecognizedPrivilege, [$name]));
        } elseif (!in_array($name, $rights, true)) {
            $derivation->report(new AccessProblem(AccessProblemRule::InvalidPrivilege, [$name === 'temporary' ? 'TEMP' : strtoupper($name), $subject]));
        }
    }

    /**
     * Tells whether the release knows a privilege name.
     */
    public function known(Derivation $derivation, string $name): bool
    {
        return in_array($name, self::KNOWN, true) || ($name === 'maintain' && $derivation->context->profile->grammar === GrammarRelease::PostgreSql172);
    }

    /**
     * Reports PUBLIC among the grantees of a grant that gives the right to grant.
     *
     * @param list<RoleSpec> $grantees
     */
    public function grantOption(Derivation $derivation, array $grantees): void
    {
        foreach ($grantees as $grantee) {
            if ($grantee->kind === RoleSpecKind::Everyone) {
                $derivation->report(new AccessProblem(AccessProblemRule::GrantOptionToPublic));
            }
        }
    }
}
