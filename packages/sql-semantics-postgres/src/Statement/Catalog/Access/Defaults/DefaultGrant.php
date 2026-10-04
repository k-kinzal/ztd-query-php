<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeLists;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The GRANT action of ALTER DEFAULT PRIVILEGES: privileges future objects of a kind receive.
 *
 * Mirrors the `GrantStmt` of `DefACLAction` with the target type
 * `ACL_TARGET_DEFAULTS`: the privileges (an empty list is ALL PRIVILEGES),
 * the kind of object, the grantees and WITH GRANT OPTION.
 * Source: https://www.postgresql.org/docs/17/sql-alterdefaultprivileges.html.
 *
 * @visibility public
 * @example Reading a default grant
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES GRANT insert ON TABLES TO joe WITH GRANT OPTION');
 *     [$operation->statement->action->objects->value, $operation->statement->action->grantOption] // => ['TABLES', true]
 */
final class DefaultGrant implements Node
{
    use Snapshot;

    /**
     * @var list<Privilege> The privileges in the order written; empty for ALL PRIVILEGES
     */
    public readonly array $privileges;

    /**
     * @var non-empty-list<RoleSpec> The grantees in the order written
     */
    public readonly array $grantees;

    /**
     * @param list<Privilege> $privileges The privileges in the order written; empty for ALL PRIVILEGES
     * @param DefaultObjectKind $objects The kind of object the defaults are set for
     * @param list<RoleSpec> $grantees The grantees in the order written, at least one
     * @param bool $grantOption Whether WITH GRANT OPTION is written
     */
    public function __construct(array $privileges, public readonly DefaultObjectKind $objects, array $grantees, public readonly bool $grantOption = false)
    {
        $this->privileges = (new PrivilegeLists())->privileges($privileges);
        $this->grantees = Check::listOf($grantees, RoleSpec::class, 'A grant names at least one grantee.', 1);
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('GRANT');
        (new PrivilegeLists())->write($out, $this->privileges);
        $out->keyword('ON', $this->objects->value, 'TO')->list($this->grantees);
        if ($this->grantOption) {
            $out->keyword('WITH', 'GRANT', 'OPTION');
        }
    }
}
