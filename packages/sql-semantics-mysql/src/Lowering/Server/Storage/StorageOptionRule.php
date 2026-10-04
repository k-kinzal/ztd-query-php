<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Storage;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\CommentOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineAttributeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\NodegroupOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\WaitOption;

/**
 * Lowers the option lists of tablespace, undo tablespace and log file group statements.
 *
 * Rule: MYSQL-STORAGE-OPTION-001. Scope (5.6, 5.7): tablespace_option_list,
 * tablespace_options, tablespace_option, alter_tablespace_option_list,
 * alter_tablespace_options, alter_tablespace_option,
 * logfile_group_option_list, logfile_group_options, logfile_group_option,
 * alter_logfile_group_option_list, alter_logfile_group_options,
 * alter_logfile_group_option, change_ts_option_list, change_ts_options,
 * change_ts_option, drop_ts_options_list, drop_ts_options, drop_ts_option,
 * opt_ts_initial_size, opt_ts_autoextend_size, opt_ts_max_size,
 * opt_ts_extent_size, opt_ts_undo_buffer_size, opt_ts_redo_buffer_size,
 * opt_ts_nodegroup, opt_ts_comment, opt_ts_engine, opt_ts_file_block_size,
 * ts_wait; (8.0 and later) opt_tablespace_options,
 * opt_alter_tablespace_options, opt_undo_tablespace_options,
 * undo_tablespace_option_list, undo_tablespace_option,
 * opt_logfile_group_options, opt_alter_logfile_group_options,
 * opt_drop_ts_options, drop_ts_option_list and the ts_option_* rules. The
 * list rules (empty, wrapping, left recursive with or without a comma) are
 * walked with an explicit stack; the commas are optional (ServerNoise,
 * LeafNoise opt_comma), as are STORAGE and the equals signs (LeafNoise).
 * Constructs: SizeOption, NodegroupOption, CommentOption, EngineOption,
 * WaitOption, EncryptionOption, EngineAttributeOption. Terminates: every
 * node is visited once.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class StorageOptionRule
{
    /**
     * The empty option lists.
     */
    private const EMPTY = [
        'tablespace_option_list:' => true, 'alter_tablespace_option_list:' => true, 'logfile_group_option_list:' => true,
        'alter_logfile_group_option_list:' => true, 'drop_ts_options_list:' => true, 'opt_tablespace_options:' => true,
        'opt_alter_tablespace_options:' => true, 'opt_undo_tablespace_options:' => true, 'opt_logfile_group_options:' => true,
        'opt_alter_logfile_group_options:' => true, 'opt_drop_ts_options:' => true,
    ];

    /**
     * The list productions whose nonterminal children hold the options in order.
     */
    private const LISTS = [
        'tablespace_option_list: tablespace_options', 'alter_tablespace_option_list: alter_tablespace_options', 'logfile_group_option_list: logfile_group_options',
        'alter_logfile_group_option_list: alter_logfile_group_options', 'change_ts_option_list: change_ts_options', 'drop_ts_options_list: drop_ts_options',
        'opt_tablespace_options: tablespace_option_list', 'opt_alter_tablespace_options: alter_tablespace_option_list',
        'opt_undo_tablespace_options: undo_tablespace_option_list', 'opt_logfile_group_options: logfile_group_option_list',
        'opt_alter_logfile_group_options: alter_logfile_group_option_list', 'opt_drop_ts_options: drop_ts_option_list',
        'tablespace_options: tablespace_option', 'tablespace_options: tablespace_options tablespace_option', 'tablespace_options: tablespace_options , tablespace_option',
        'alter_tablespace_options: alter_tablespace_option', 'alter_tablespace_options: alter_tablespace_options alter_tablespace_option',
        'alter_tablespace_options: alter_tablespace_options , alter_tablespace_option', 'logfile_group_options: logfile_group_option',
        'logfile_group_options: logfile_group_options logfile_group_option', 'logfile_group_options: logfile_group_options , logfile_group_option',
        'alter_logfile_group_options: alter_logfile_group_option', 'alter_logfile_group_options: alter_logfile_group_options alter_logfile_group_option',
        'alter_logfile_group_options: alter_logfile_group_options , alter_logfile_group_option', 'change_ts_options: change_ts_option',
        'change_ts_options: change_ts_options change_ts_option', 'change_ts_options: change_ts_options , change_ts_option', 'drop_ts_options: drop_ts_option',
        'drop_ts_options: drop_ts_options drop_ts_option', 'drop_ts_options: drop_ts_options_list , drop_ts_option',
        'tablespace_option_list: tablespace_option', 'tablespace_option_list: tablespace_option_list opt_comma tablespace_option',
        'alter_tablespace_option_list: alter_tablespace_option', 'alter_tablespace_option_list: alter_tablespace_option_list opt_comma alter_tablespace_option',
        'undo_tablespace_option_list: undo_tablespace_option', 'undo_tablespace_option_list: undo_tablespace_option_list opt_comma undo_tablespace_option',
        'logfile_group_option_list: logfile_group_option', 'logfile_group_option_list: logfile_group_option_list opt_comma logfile_group_option',
        'alter_logfile_group_option_list: alter_logfile_group_option',
        'alter_logfile_group_option_list: alter_logfile_group_option_list opt_comma alter_logfile_group_option',
        'drop_ts_option_list: drop_ts_option', 'drop_ts_option_list: drop_ts_option_list opt_comma drop_ts_option',
    ];

    /**
     * The productions of the option slots of each list: each names the one option rule it admits.
     */
    private const SLOTS = [
        'change_ts_option: opt_ts_initial_size' => true, 'change_ts_option: opt_ts_autoextend_size' => true,
        'change_ts_option: opt_ts_max_size' => true, 'tablespace_option: opt_ts_initial_size' => true,
        'tablespace_option: opt_ts_autoextend_size' => true, 'tablespace_option: opt_ts_max_size' => true,
        'tablespace_option: opt_ts_extent_size' => true, 'tablespace_option: opt_ts_nodegroup' => true, 'tablespace_option: opt_ts_engine' => true,
        'tablespace_option: ts_wait' => true, 'tablespace_option: opt_ts_comment' => true, 'alter_tablespace_option: opt_ts_initial_size' => true,
        'alter_tablespace_option: opt_ts_autoextend_size' => true, 'alter_tablespace_option: opt_ts_max_size' => true,
        'alter_tablespace_option: opt_ts_engine' => true, 'alter_tablespace_option: ts_wait' => true,
        'logfile_group_option: opt_ts_initial_size' => true, 'logfile_group_option: opt_ts_undo_buffer_size' => true,
        'logfile_group_option: opt_ts_redo_buffer_size' => true, 'logfile_group_option: opt_ts_nodegroup' => true,
        'logfile_group_option: opt_ts_engine' => true, 'logfile_group_option: ts_wait' => true, 'logfile_group_option: opt_ts_comment' => true,
        'alter_logfile_group_option: opt_ts_initial_size' => true, 'alter_logfile_group_option: opt_ts_engine' => true,
        'alter_logfile_group_option: ts_wait' => true, 'drop_ts_option: opt_ts_engine' => true, 'drop_ts_option: ts_wait' => true,
        'tablespace_option: opt_ts_file_block_size' => true, 'tablespace_option: ts_option_initial_size' => true,
        'tablespace_option: ts_option_autoextend_size' => true, 'tablespace_option: ts_option_max_size' => true,
        'tablespace_option: ts_option_extent_size' => true, 'tablespace_option: ts_option_nodegroup' => true,
        'tablespace_option: ts_option_engine' => true, 'tablespace_option: ts_option_wait' => true, 'tablespace_option: ts_option_comment' => true,
        'tablespace_option: ts_option_file_block_size' => true, 'tablespace_option: ts_option_encryption' => true,
        'tablespace_option: ts_option_engine_attribute' => true, 'alter_tablespace_option: ts_option_initial_size' => true,
        'alter_tablespace_option: ts_option_autoextend_size' => true, 'alter_tablespace_option: ts_option_max_size' => true,
        'alter_tablespace_option: ts_option_engine' => true, 'alter_tablespace_option: ts_option_wait' => true,
        'alter_tablespace_option: ts_option_encryption' => true, 'alter_tablespace_option: ts_option_engine_attribute' => true,
        'undo_tablespace_option: ts_option_engine' => true, 'logfile_group_option: ts_option_initial_size' => true,
        'logfile_group_option: ts_option_undo_buffer_size' => true, 'logfile_group_option: ts_option_redo_buffer_size' => true,
        'logfile_group_option: ts_option_nodegroup' => true, 'logfile_group_option: ts_option_engine' => true,
        'logfile_group_option: ts_option_wait' => true, 'logfile_group_option: ts_option_comment' => true,
        'alter_logfile_group_option: ts_option_initial_size' => true, 'alter_logfile_group_option: ts_option_engine' => true,
        'alter_logfile_group_option: ts_option_wait' => true, 'drop_ts_option: ts_option_engine' => true, 'drop_ts_option: ts_option_wait' => true,
    ];

    /**
     * The size option productions: the option and the position of the size.
     */
    private const SIZES = [
        'opt_ts_initial_size: INITIAL_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Initial, 2],
        'opt_ts_autoextend_size: AUTOEXTEND_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Autoextend, 2],
        'opt_ts_max_size: MAX_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Maximum, 2],
        'opt_ts_extent_size: EXTENT_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Extent, 2],
        'opt_ts_undo_buffer_size: UNDO_BUFFER_SIZE_SYM opt_equal size_number' => [SizeOptionKind::UndoBuffer, 2],
        'opt_ts_redo_buffer_size: REDO_BUFFER_SIZE_SYM opt_equal size_number' => [SizeOptionKind::RedoBuffer, 2],
        'opt_ts_file_block_size: FILE_BLOCK_SIZE_SYM opt_equal size_number' => [SizeOptionKind::FileBlock, 2],
        'ts_option_initial_size: INITIAL_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Initial, 2],
        'ts_option_autoextend_size: option_autoextend_size' => [SizeOptionKind::Autoextend, 0],
        'ts_option_max_size: MAX_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Maximum, 2],
        'ts_option_extent_size: EXTENT_SIZE_SYM opt_equal size_number' => [SizeOptionKind::Extent, 2],
        'ts_option_undo_buffer_size: UNDO_BUFFER_SIZE_SYM opt_equal size_number' => [SizeOptionKind::UndoBuffer, 2],
        'ts_option_redo_buffer_size: REDO_BUFFER_SIZE_SYM opt_equal size_number' => [SizeOptionKind::RedoBuffer, 2],
        'ts_option_file_block_size: FILE_BLOCK_SIZE_SYM opt_equal size_number' => [SizeOptionKind::FileBlock, 2],
    ];

    /**
     * The productions of the other options, by the kind of option.
     */
    private const OTHERS = [
        'opt_ts_nodegroup: NODEGROUP_SYM opt_equal real_ulong_num' => 'nodegroup', 'ts_option_nodegroup: NODEGROUP_SYM opt_equal real_ulong_num' => 'nodegroup',
        'opt_ts_comment: COMMENT_SYM opt_equal TEXT_STRING_sys' => 'comment', 'ts_option_comment: COMMENT_SYM opt_equal TEXT_STRING_sys' => 'comment',
        'opt_ts_engine: opt_storage ENGINE_SYM opt_equal storage_engines' => 'engine', 'ts_option_engine: opt_storage ENGINE_SYM opt_equal ident_or_text' => 'engine',
        'ts_wait: WAIT_SYM' => 'wait', 'ts_wait: NO_WAIT_SYM' => 'noWait', 'ts_option_wait: WAIT_SYM' => 'wait', 'ts_option_wait: NO_WAIT_SYM' => 'noWait',
        'ts_option_encryption: ENCRYPTION_SYM opt_equal TEXT_STRING_sys' => 'encryption',
        'ts_option_engine_attribute: ENGINE_ATTRIBUTE_SYM opt_equal json_attribute' => 'attribute',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an option list, in written order.
     *
     * @return list<StorageOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $list): array
    {
        $options = [];
        $pending = [$list];
        while ($pending !== []) {
            $node = array_pop($pending);
            $form = $this->lowering->form($node);
            if (isset(self::EMPTY[$form->signature])) {
                continue;
            }
            if (isset(self::SLOTS[$form->signature])) {
                $options[] = $this->option($form->node(0));
                continue;
            }
            if (!in_array($form->signature, self::LISTS, true)) {
                throw ImplementationGap::production($form);
            }
            for ($index = count($node->children) - 1; $index >= 0; $index--) {
                $child = $node->children[$index];
                if ($child instanceof Node && $child->name === 'opt_comma') {
                    $this->lowering->options->present($child);
                } elseif ($child instanceof Node) {
                    $pending[] = $child;
                }
            }
        }

        return $options;
    }

    /**
     * Lowers one option: a node of an opt_ts_* or ts_option_* rule, or of ts_wait.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option): StorageOption
    {
        $form = $this->lowering->form($option);
        if (isset(self::SIZES[$form->signature])) {
            [$kind, $position] = self::SIZES[$form->signature];
            if ($position === 2) {
                $this->lowering->options->present($form->node(1));
            }

            return new SizeOption($kind, $this->lowering->numbers->size($form->node($position)));
        }
        $kind = self::OTHERS[$form->signature] ?? throw ImplementationGap::production($form);

        return match ($kind) {
            'nodegroup' => new NodegroupOption($this->lowering->numbers->numeral($this->value($form, 2))),
            'comment' => new CommentOption($this->lowering->literals->text($this->value($form, 2))),
            'engine' => $this->engine($form),
            'wait' => new WaitOption(true),
            'noWait' => new WaitOption(false),
            'encryption' => new EncryptionOption($this->lowering->literals->text($this->value($form, 2))),
            'attribute' => new EngineAttributeOption($this->lowering->literals->text($this->value($form, 2))),
        };
    }

    /**
     * Answers the node of an option value after its optional equals sign.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function value(Form $form, int $position): Node
    {
        $this->lowering->options->present($form->node($position - 1));

        return $form->node($position);
    }

    /**
     * Lowers `[STORAGE] ENGINE [=] name`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function engine(Form $form): EngineOption
    {
        $this->lowering->options->present($form->node(0));

        return new EngineOption($this->lowering->names->identifier($this->value($form, 3)));
    }
}
