<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Account;

use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Lowering\Productions;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;

/**
 * Interprets privilege-list refusals and default-schema dependencies during input lowering.
 *
 * Only decoded operands and integer boundaries escape these calls. A role with a
 * host cannot be published in a privilege statement, and its refusal stops input.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/grant.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxPrivileges
{
    /**
     * Answers the first recipient or privilege operand failure of a completed command.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if (!in_array($node->name, ['grant', 'revoke'], true)) {
            return null;
        }
        $form = $lowering->form($node);
        $indices = match ($form->signature) {
            'grant: GRANT role_or_privilege_list ON_SYM opt_acl_type grant_ident TO_SYM user_list grant_options opt_grant_as' => [1, 6],
            'revoke: REVOKE if_exists role_or_privilege_list ON_SYM opt_acl_type grant_ident FROM user_list opt_ignore_unknown_user' => [2, 7],
            default => null,
        };
        if ($indices === null) {
            return null;
        }
        foreach ($lowering->users->accounts($form->node($indices[1])) as $account) {
            $failure = $account instanceof AccountName ? (new SyntaxFactors())->account($account) : null;
            if ($failure !== null) {
                return $failure;
            }
        }
        try {
            (new PrivilegeRule($lowering))->granted($form->node($indices[0]), (new SourceRanges())->span($node)[1] ?? 0);
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, true);
        }

        return null;
    }

    /**
     * Answers the first completed privilege target that requires a connection database.
     * A qualified routine definition supplies its own database to its body.
     */
    public function database(ReductionParser $parser, string $sql, int $end, LanguageProfile $profile): ?int
    {
        $lowering = new Lowering(Productions::load(dirname(__DIR__, 3) . '/resources/productions/' . $profile->grammar->value . '.php'), new Leaves(), $profile);
        $boundary = null;
        $routineSchema = null;
        try {
            $parser->parse($sql, static function (Node $node) use ($lowering, $end, &$boundary, &$routineSchema): void {
                if ($routineSchema === null && $node->name === 'sp_name') {
                    $routineSchema = $lowering->names->qualified($node)->schema !== null;
                }
                if ($boundary !== null || $routineSchema === true || $node->name !== 'grant_ident') {
                    return;
                }
                $range = (new SourceRanges())->span($node);
                if ($range === null || $range[1] > $end) {
                    return;
                }
                $level = (new PrivilegeRule($lowering))->level($node);
                if ($level instanceof CurrentDatabaseLevel || ($level instanceof ObjectLevel && $level->name->schema === null)) {
                    $boundary = $range[1];
                }
            });
        } catch (SourceException) {
            // Completed target operands precede the caller's original refusal.
        }

        return $boundary;
    }
}
