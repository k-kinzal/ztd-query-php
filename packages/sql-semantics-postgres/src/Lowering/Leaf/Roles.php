<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers role specifications.
 *
 * Rule: PG-ROLE-001. Scope: `RoleSpec`, `RoleId`, `role_list`. Constructor:
 * `RoleSpec`. The word `public`, quoted or not, designates every role; the
 * word `none` is reserved and rejected by the grammar action. `RoleId` is a
 * position that takes a role name only, so every designation there is
 * rejected by the grammar action as well. Termination: the list is flattened
 * iteratively. Source: https://www.postgresql.org/docs/17/sql-grant.html,
 * https://www.postgresql.org/docs/17/sql-createrole.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Roles
{
    /**
     * @param Lowering $lowering The hub
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers `RoleSpec`.
     *
     * @throws AnalysisException When the role is named none, which the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function role(Node $role): RoleSpec
    {
        $form = $this->lowering->productions->form($role);
        if ($form->signature === 'RoleSpec: NonReservedWord') {
            $word = $this->lowering->names->text($form->node(0));
            if ($word === 'none') {
                throw new AnalysisException('role name "none" is reserved');
            }

            return $word === 'public' ? new RoleSpec(RoleSpecKind::Everyone) : new RoleSpec(RoleSpecKind::Named, $this->lowering->names->name($form->node(0)));
        }

        return new RoleSpec(match ($form->signature) {
            'RoleSpec: CURRENT_ROLE' => RoleSpecKind::CurrentRole,
            'RoleSpec: CURRENT_USER' => RoleSpecKind::CurrentUser,
            'RoleSpec: SESSION_USER' => RoleSpecKind::SessionUser,
            default => throw ImplementationGap::production($form),
        });
    }

    /**
     * Lowers `RoleId`: a position that names a role and accepts no designation.
     *
     * @throws AnalysisException When a designation is written, which the server rejects while parsing
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $role): Name
    {
        $form = $this->lowering->productions->form($role);
        if ($form->signature !== 'RoleId: RoleSpec') {
            throw ImplementationGap::production($form);
        }

        return $this->role($form->node(0))->name ?? throw new AnalysisException('a role designation cannot be used as a role name here');
    }

    /**
     * Lowers `role_list`.
     *
     * @return list<RoleSpec>
     */
    public function roles(Node $list): array
    {
        $roles = [];
        foreach ($this->lowering->items($list, 'role_list: RoleSpec', 'role_list: role_list , RoleSpec') as $role) {
            $roles[] = $this->role($role);
        }

        return $roles;
    }
}
