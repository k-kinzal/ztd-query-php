<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\AnonymousGtids;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\PrimaryKeyCheck;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ServerIds;

/**
 * Lowers the value of an option of CHANGE REPLICATION SOURCE TO.
 *
 * Rule: MYSQL-SOURCE-VALUE-001. Scope: ignore_server_id_list,
 * ignore_server_id, privilege_check_def, table_primary_key_check_def,
 * assign_gtids_to_anonymous_transactions_def, source_tls_ciphersuites_def,
 * and the string and number leaves at the value position of an option. A
 * string is a Text, a number a Numeral, the heartbeat period a
 * NumberLiteral, NULL the value null, the keyword settings their enum cases;
 * the UUID of ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS is a plain string token
 * decoded under the escape setting of the profile. The server identifier
 * list may start with an empty element, `( , 2)`, which adds no identifier. Terminates: the server
 * identifier list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class SourceValueRule
{
    /**
     * The settings of REQUIRE_TABLE_PRIMARY_KEY_CHECK by production.
     */
    private const PRIMARY_KEY_CHECKS = [
        'table_primary_key_check_def: STREAM_SYM' => PrimaryKeyCheck::Stream, 'table_primary_key_check_def: ON_SYM' => PrimaryKeyCheck::On,
        'table_primary_key_check_def: OFF_SYM' => PrimaryKeyCheck::Off, 'table_primary_key_check_def: GENERATE_SYM' => PrimaryKeyCheck::Generate,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers the value of an option production: the child after `=`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Form $option): Text|Numeral|NumberLiteral|ServerIds|AccountName|PrimaryKeyCheck|AnonymousGtids|null
    {
        $value = $option->node->children[2] ?? null;
        if (!$value instanceof Node) {
            return $this->serverIds($option->node(3));
        }

        return match ($value->name) {
            'TEXT_STRING_sys_nonewline', 'TEXT_STRING_sys' => $this->lowering->literals->text($value),
            'ulong_num', 'ulonglong_num', 'real_ulong_num' => $this->lowering->numbers->numeral($value),
            'NUM_literal' => $this->lowering->literals->number($value),
            'privilege_check_def' => $this->privilegeChecks($value),
            'table_primary_key_check_def' => $this->primaryKeyCheck($value),
            'assign_gtids_to_anonymous_transactions_def' => $this->anonymousGtids($value),
            'source_tls_ciphersuites_def' => $this->cipherSuites($value),
            default => throw ImplementationGap::production($option),
        };
    }

    /**
     * Lowers the server identifiers of IGNORE_SERVER_IDS; an empty list clears the setting, an empty element adds nothing.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function serverIds(Node $list): ServerIds
    {
        $ids = [];
        $spine = ['ignore_server_id_list:', 'ignore_server_id_list: ignore_server_id', 'ignore_server_id_list: ignore_server_id_list , ignore_server_id'];
        foreach ((new Spine($this->lowering))->items($list, $spine) as $item) {
            $form = $this->lowering->form($item);
            if ($form->signature !== 'ignore_server_id: ulong_num') {
                throw ImplementationGap::production($form);
            }
            $ids[] = $this->lowering->numbers->numeral($form->node(0));
        }

        return new ServerIds($ids);
    }

    /**
     * Lowers the account of PRIVILEGE_CHECKS_USER; NULL is null.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function privilegeChecks(Node $value): ?AccountName
    {
        $form = $this->lowering->form($value);
        if ($form->signature === 'privilege_check_def: NULL_SYM') {
            return null;
        }
        if ($form->signature !== 'privilege_check_def: user_ident_or_text') {
            throw ImplementationGap::production($form);
        }
        $account = $this->lowering->users->account($form->node(0));
        if (!$account instanceof AccountName) {
            throw ImplementationGap::production($form);
        }

        return $account;
    }

    /**
     * Lowers the setting of REQUIRE_TABLE_PRIMARY_KEY_CHECK.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function primaryKeyCheck(Node $value): PrimaryKeyCheck
    {
        $form = $this->lowering->form($value);

        return self::PRIMARY_KEY_CHECKS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers the setting of ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS: OFF, LOCAL or a UUID string.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function anonymousGtids(Node $value): AnonymousGtids|Text
    {
        $form = $this->lowering->form($value);
        $literals = $this->lowering->literals;

        return match ($form->signature) {
            'assign_gtids_to_anonymous_transactions_def: OFF_SYM' => AnonymousGtids::Off,
            'assign_gtids_to_anonymous_transactions_def: LOCAL_SYM' => AnonymousGtids::Local,
            'assign_gtids_to_anonymous_transactions_def: TEXT_STRING' => $this->lowering->leaves->record(new Text($literals->decode($form->token(0)->text), $literals->escapes())),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the setting of SOURCE_TLS_CIPHERSUITES: a string, or NULL as null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function cipherSuites(Node $value): ?Text
    {
        $form = $this->lowering->form($value);

        return match ($form->signature) {
            'source_tls_ciphersuites_def: TEXT_STRING_sys_nonewline' => $this->lowering->literals->text($form->node(0)),
            'source_tls_ciphersuites_def: NULL_SYM' => null,
            default => throw ImplementationGap::production($form),
        };
    }
}
