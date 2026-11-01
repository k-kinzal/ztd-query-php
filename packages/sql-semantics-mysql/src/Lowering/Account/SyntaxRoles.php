<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Interprets role-list input failures without publishing a role statement.
 *
 * Recipient names are checked first. Role operands then report their problems
 * in list order, continuing through later program statements. Only decoded
 * diagnostic operands and byte positions escape; no parser tree is retained.
 * Observed on MySQL 8.0.44, 8.4.7 and 9.1.0 through SQL.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxRoles
{
    /**
     * Answers the SUPER warning at the privilege operand's completion.
     */
    public function deprecation(Node $node, \SqlSemantics\Contract\GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated
    {
        $token = $node->children[0] ?? null;
        $notice = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::SuperPrivilege;

        return $node->name === 'role_or_privilege' && $token instanceof \SqlParser\Lexer\Token && $token->name === 'SUPER_SYM' && $notice->warnedIn($release) ? $notice : null;
    }

    /**
     * Answers the ordered input problems of a completed role grant or revoke.
     *
     * @return list<ParseFailure>
     */
    public function completed(Node $node, Lowering $lowering): array
    {
        if (!in_array($node->name, ['grant', 'revoke'], true)) {
            return [];
        }
        $form = $lowering->form($node);
        $indices = match ($form->signature) {
            'grant: GRANT role_or_privilege_list TO_SYM user_list opt_with_admin_option' => [1, 3],
            'revoke: REVOKE if_exists role_or_privilege_list FROM user_list opt_ignore_unknown_user' => [2, 4],
            default => null,
        };
        if ($indices === null) {
            return [];
        }
        $names = new \SqlSemantics\Platform\MySql\Rules\Account\AccountNames();
        foreach ($lowering->users->accounts($form->node($indices[1])) as $account) {
            $problem = $account instanceof \SqlSemantics\Platform\MySql\Statement\Name\AccountName ? $names->problem($account->user, $account->host, $lowering->profile->grammar) : null;
            if ($problem !== null) {
                return [new ParseFailure($problem, true)];
            }
        }
        $rule = new PrivilegeRule($lowering);
        $failures = [];
        foreach ($rule->spine($form->node($indices[0])) as $operand) {
            $item = $rule->item($operand, true);
            $problem = $item instanceof GrantedRole
                ? $names->problem($item->role->user, $item->role->host, $lowering->profile->grammar)
                : new RoleOrPrivilegeMismatch((new PrivilegeChecks())->describe($item), true, (new SourceRanges())->span($operand)[0] ?? 0);
            if ($problem !== null) {
                $failures[] = new ParseFailure($problem, endsProgramStatement: false);
            }
        }

        return $failures;
    }
}
