<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Alter\DropBehavior;

/**
 * Lowers the optional keywords, synonym keywords and empty marker rules that several statement families share.
 *
 * Rule: MYSQL-OPTION-001. Scope: opt_as, opt_if_not_exists, if_exists,
 * opt_temporary, opt_ignore, opt_all, opt_default, opt_equal, equal,
 * opt_wild, opt_storage, opt_table, opt_comma, optional_braces,
 * opt_no_write_to_binlog, opt_key_or_index, key_or_index, keys_or_index,
 * table_or_tables, not, charset, character_set, opt_restrict, and the empty
 * marker rules remember_name, remember_end, init_lex_create_info,
 * clear_privileges, clear_password_expire_options, get_select_lex,
 * subselect_start, subselect_end, have_partitioning, init_key_options,
 * sp_init_param, no_definer and empty_select_options. An optional keyword
 * lowers to whether it is written; RESTRICT and CASCADE to the keyword
 * written. A synonym or marker rule holds no operand: skip() confirms the
 * production is one of them. Which of the keywords are noise or synonyms for
 * the token correspondence is stated in LeafNoise. Terminates: constant
 * work. Source: the statement pages cited in LeafNoise. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OptionRule
{
    /**
     * Whether each optional keyword production writes its keyword.
     */
    private const PRESENT = [
        'opt_as:' => false, 'opt_as: AS' => true, 'opt_if_not_exists:' => false, 'opt_if_not_exists: IF not EXISTS' => true,
        'if_exists:' => false, 'if_exists: IF EXISTS' => true, 'opt_temporary:' => false, 'opt_temporary: TEMPORARY' => true,
        'opt_ignore:' => false, 'opt_ignore: IGNORE_SYM' => true, 'opt_all:' => false, 'opt_all: ALL' => true,
        'opt_default:' => false, 'opt_default: DEFAULT' => true, 'opt_default: DEFAULT_SYM' => true,
        'opt_equal:' => false, 'opt_equal: equal' => true, 'opt_wild:' => false, 'opt_wild: . *' => true,
        'opt_storage:' => false, 'opt_storage: STORAGE_SYM' => true, 'opt_table:' => false, 'opt_table: TABLE_SYM' => true,
        'opt_comma:' => false, 'opt_comma: ,' => true, 'optional_braces:' => false, 'optional_braces: ( )' => true,
        'opt_no_write_to_binlog:' => false, 'opt_no_write_to_binlog: NO_WRITE_TO_BINLOG' => true, 'opt_no_write_to_binlog: LOCAL_SYM' => true,
        'opt_key_or_index:' => false, 'opt_key_or_index: key_or_index' => true,
    ];

    /**
     * The synonym keyword productions and the empty marker productions, which hold no operand.
     */
    private const INERT = [
        'key_or_index: KEY_SYM' => true, 'key_or_index: INDEX_SYM' => true, 'keys_or_index: KEYS' => true, 'keys_or_index: INDEX_SYM' => true,
        'keys_or_index: INDEXES' => true, 'table_or_tables: TABLE_SYM' => true, 'table_or_tables: TABLES' => true,
        'equal: EQ' => true, 'equal: SET_VAR' => true, 'not: NOT_SYM' => true, 'not: NOT2_SYM' => true,
        'charset: CHAR_SYM SET' => true, 'charset: CHARSET' => true, 'character_set: CHAR_SYM SET_SYM' => true, 'character_set: CHARSET' => true,
        'remember_name:' => true, 'remember_end:' => true, 'init_lex_create_info:' => true, 'clear_privileges:' => true,
        'clear_privileges: clear_password_expire_options' => true, 'clear_password_expire_options:' => true, 'get_select_lex:' => true,
        'subselect_start:' => true, 'subselect_end:' => true, 'have_partitioning:' => true, 'init_key_options:' => true,
        'sp_init_param:' => true, 'no_definer:' => true, 'empty_select_options:' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Tells whether an optional keyword is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function present(Node $option): bool
    {
        $form = $this->lowering->productions->form($option);

        return self::PRESENT[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Confirms that a node is a synonym keyword or an empty marker and holds no operand.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function skip(Node $inert): void
    {
        $form = $this->lowering->productions->form($inert);
        if (!isset(self::INERT[$form->signature])) {
            throw ImplementationGap::production($form);
        }
    }

    /**
     * Lowers the optional RESTRICT or CASCADE keyword of a DROP.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function dropBehavior(Node $behavior): ?DropBehavior
    {
        $form = $this->lowering->productions->form($behavior);

        return match ($form->signature) {
            'opt_restrict:' => null,
            'opt_restrict: RESTRICT' => DropBehavior::Restrict,
            'opt_restrict: CASCADE' => DropBehavior::Cascade,
            default => throw ImplementationGap::production($form),
        };
    }
}
