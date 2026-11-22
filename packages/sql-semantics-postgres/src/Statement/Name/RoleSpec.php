<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Name;

use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A role named directly, or designated as PUBLIC, CURRENT_ROLE, CURRENT_USER or SESSION_USER.
 *
 * Mirrors PostgreSQL's `RoleSpec` node. The word `public` designates every
 * role whether or not it is quoted, and `none` is reserved, so neither can be
 * the name of a named role.
 * Source: https://www.postgresql.org/docs/17/sql-grant.html, https://www.postgresql.org/docs/17/sql-createrole.html.
 *
 * @visibility SqlSemantics
 * @example Designating the current user
 *     $role = new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::CurrentUser);
 *     $role->name // => null
 * @example Rejecting the reserved word as a role name
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('public')) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class RoleSpec implements Node
{
    use Snapshot;

    /**
     * @param RoleSpecKind $kind How the role is designated
     * @param Name|null $name The role name; given exactly when the role is named
     */
    public function __construct(public readonly RoleSpecKind $kind, public readonly ?Name $name = null)
    {
        Check::input(($kind === RoleSpecKind::Named) === ($name !== null), 'A role specification has a name exactly when it names a role.');
        Check::input($name === null || ($name->value !== 'public' && $name->value !== 'none'), 'The words public and none cannot name a role.');
    }

    /**
     * Writes the role name or the designating keyword.
     */
    public function render(Output $out): void
    {
        match ($this->kind) {
            RoleSpecKind::Named => $out->name($this->name ?? new Name('public'), NameUse::Column),
            RoleSpecKind::Everyone => $out->name(new Name('public'), NameUse::Column),
            RoleSpecKind::CurrentRole => $out->keyword('CURRENT_ROLE'),
            RoleSpecKind::CurrentUser => $out->keyword('CURRENT_USER'),
            RoleSpecKind::SessionUser => $out->keyword('SESSION_USER'),
        };
    }
}
