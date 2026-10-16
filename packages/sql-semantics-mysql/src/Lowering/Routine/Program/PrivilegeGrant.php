<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Platform\MySql\Lowering\Account\GrantRule;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredGrantAs;

/**
 * Selects the stored-program interpretation before immediate GRANT constraints.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class PrivilegeGrant
{
    /**
     * Reads a privilege instruction, preserving its deferred non-global AS form.
     */
    public function read(Node $node, Lowering $lowering): GrantPrivileges|DeferredGrantAs|null
    {
        while ($node->name !== 'grant') {
            $children = array_values(array_filter($node->children, static fn ($child): bool => $child instanceof Node));
            if (count($children) !== 1) {
                return null;
            }
            $node = $children[0];
        }
        $form = $lowering->form($node);
        $all = match ($form->signature) {
            'grant: GRANT role_or_privilege_list ON_SYM opt_acl_type grant_ident TO_SYM user_list grant_options opt_grant_as' => false,
            'grant: GRANT ALL opt_privileges ON_SYM opt_acl_type grant_ident TO_SYM user_list grant_options opt_grant_as' => true,
            default => null,
        };

        return $all === null ? null : (new GrantRule($lowering))->request($form, $all);
    }
}
