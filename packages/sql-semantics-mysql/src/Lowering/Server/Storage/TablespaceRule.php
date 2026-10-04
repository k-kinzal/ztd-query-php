<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Storage;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\TablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\RenameTablespace;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the tablespace and undo tablespace statements.
 *
 * Rule: MYSQL-TABLESPACE-001. Scope: the TABLESPACE alternatives of create,
 * alter and drop, tablespace_info, alter_tablespace_info,
 * change_tablespace_info, change_tablespace_access, tablespace_name,
 * ts_access_mode (5.6, 5.7), the TABLESPACE and UNDO TABLESPACE
 * alternatives of create, alter_tablespace_stmt,
 * alter_undo_tablespace_stmt, drop_tablespace_stmt,
 * drop_undo_tablespace_stmt, opt_ts_datafile_name, undo_tablespace_state
 * (8.0 and later), ts_datafile, opt_logfile_group_name. The options go
 * through MYSQL-STORAGE-OPTION-001. Constructs: CreateTablespace,
 * CreateUndoTablespace, AlterTablespaceDatafile, AlterTablespace,
 * AlterTablespaceAccess, RenameTablespace, AlterUndoTablespace,
 * DropTablespace, DropUndoTablespace. Terminates: every child is a strict
 * subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-tablespace.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class TablespaceRule
{
    /**
     * The 5.x statement productions whose second child holds the details, by the details rule.
     */
    private const DETAILED = [
        'create: CREATE TABLESPACE tablespace_info' => true, 'create: CREATE TABLESPACE_SYM tablespace_info' => true,
        'alter: ALTER TABLESPACE alter_tablespace_info' => true, 'alter: ALTER TABLESPACE_SYM alter_tablespace_info' => true,
        'alter: ALTER TABLESPACE change_tablespace_info' => true, 'alter: ALTER TABLESPACE_SYM change_tablespace_info' => true,
        'alter: ALTER TABLESPACE change_tablespace_access' => true, 'alter: ALTER TABLESPACE_SYM change_tablespace_access' => true,
    ];

    /**
     * The data file productions of ALTER TABLESPACE: the action, the positions of the name, of the data file and of the options.
     */
    private const DATAFILES = [
        'alter_tablespace_info: tablespace_name ADD ts_datafile alter_tablespace_option_list' => [DatafileAction::Add, 0, 2, 3],
        'alter_tablespace_info: tablespace_name DROP ts_datafile alter_tablespace_option_list' => [DatafileAction::Drop, 0, 2, 3],
        'change_tablespace_info: tablespace_name CHANGE ts_datafile change_ts_option_list' => [DatafileAction::Change, 0, 2, 3],
        'alter_tablespace_stmt: ALTER TABLESPACE_SYM ident ADD ts_datafile opt_alter_tablespace_options' => [DatafileAction::Add, 2, 4, 5],
        'alter_tablespace_stmt: ALTER TABLESPACE_SYM ident DROP ts_datafile opt_alter_tablespace_options' => [DatafileAction::Drop, 2, 4, 5],
    ];

    /**
     * The access mode productions.
     */
    private const ACCESS = ['ts_access_mode: READ_ONLY_SYM' => TablespaceAccess::ReadOnly, 'ts_access_mode: READ_WRITE_SYM' => TablespaceAccess::ReadWrite, 'ts_access_mode: NOT_SYM ACCESSIBLE_SYM' => TablespaceAccess::NotAccessible];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a tablespace statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        if (isset(self::DETAILED[$form->signature])) {
            return $this->statement($this->lowering->form($form->node(2)));
        }
        if (isset(self::DATAFILES[$form->signature])) {
            [$action, $name, $file, $options] = self::DATAFILES[$form->signature];

            return new AlterTablespaceDatafile($this->name($form->node($name)), $action, $this->datafile($form->node($file)), $this->options($form->node($options)));
        }

        return match ($form->signature) {
            'tablespace_info: tablespace_name ADD ts_datafile opt_logfile_group_name tablespace_option_list' => new CreateTablespace(
                $this->name($form->node(0)),
                $this->datafile($form->node(2)),
                $this->group($form->node(3)),
                $this->options($form->node(4)),
            ),
            'create: CREATE TABLESPACE_SYM ident opt_ts_datafile_name opt_logfile_group_name opt_tablespace_options' => new CreateTablespace(
                $this->name($form->node(2)),
                $this->optionalDatafile($form->node(3)),
                $this->group($form->node(4)),
                $this->options($form->node(5)),
            ),
            'create: CREATE UNDO_SYM TABLESPACE_SYM ident ADD ts_datafile opt_undo_tablespace_options' => new CreateUndoTablespace(
                $this->name($form->node(3)),
                $this->datafile($form->node(5)),
                $this->options($form->node(6)),
            ),
            default => $this->change($form),
        };
    }

    /**
     * Lowers the ALTER and DROP forms other than the data file forms.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function change(Form $form): Statement
    {
        return match ($form->signature) {
            'change_tablespace_access: tablespace_name ts_access_mode' => new AlterTablespaceAccess($this->name($form->node(0)), $this->access($form->node(1))),
            'alter_tablespace_stmt: ALTER TABLESPACE_SYM ident RENAME TO_SYM ident' => new RenameTablespace($this->name($form->node(2)), $this->name($form->node(5))),
            'alter_tablespace_stmt: ALTER TABLESPACE_SYM ident alter_tablespace_option_list' => new AlterTablespace($this->name($form->node(2)), $this->options($form->node(3))),
            'alter_undo_tablespace_stmt: ALTER UNDO_SYM TABLESPACE_SYM ident SET_SYM undo_tablespace_state opt_undo_tablespace_options' => new AlterUndoTablespace(
                $this->name($form->node(3)),
                $this->active($form->node(5)),
                $this->options($form->node(6)),
            ),
            'drop: DROP TABLESPACE tablespace_name drop_ts_options_list', 'drop: DROP TABLESPACE_SYM tablespace_name drop_ts_options_list' => new DropTablespace(
                $this->name($form->node(2)),
                $this->options($form->node(3)),
            ),
            'drop_tablespace_stmt: DROP TABLESPACE_SYM ident opt_drop_ts_options' => new DropTablespace($this->name($form->node(2)), $this->options($form->node(3))),
            'drop_undo_tablespace_stmt: DROP UNDO_SYM TABLESPACE_SYM ident opt_undo_tablespace_options' => new DropUndoTablespace($this->name($form->node(3)), $this->options($form->node(4))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a tablespace or log file group name: a node of `ident`, `tablespace_name` or `logfile_group_name`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function name(Node $name): Name
    {
        $form = $this->lowering->form($name);
        if ($form->signature === 'tablespace_name: ident' || $form->signature === 'logfile_group_name: ident') {
            return $this->lowering->names->identifier($form->node(0));
        }

        return $this->lowering->names->identifier($name);
    }

    /**
     * Lowers a data file: a node of `ts_datafile`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function datafile(Node $datafile): Text
    {
        $form = $this->lowering->form($datafile);
        if ($form->signature !== 'ts_datafile: DATAFILE_SYM TEXT_STRING_sys') {
            throw ImplementationGap::production($form);
        }

        return $this->lowering->literals->text($form->node(1));
    }

    /**
     * Lowers the optional data file of MySQL 8.0: a node of `opt_ts_datafile_name`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function optionalDatafile(Node $datafile): ?Text
    {
        $form = $this->lowering->form($datafile);

        return match ($form->signature) {
            'opt_ts_datafile_name:' => null,
            'opt_ts_datafile_name: ADD ts_datafile' => $this->datafile($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers USE LOGFILE GROUP: a node of `opt_logfile_group_name`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function group(Node $group): ?Name
    {
        $form = $this->lowering->form($group);

        return match ($form->signature) {
            'opt_logfile_group_name:' => null,
            'opt_logfile_group_name: USE_SYM LOGFILE_SYM GROUP_SYM ident' => $this->lowering->names->identifier($form->node(3)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an access mode: a node of `ts_access_mode`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function access(Node $mode): TablespaceAccess
    {
        $form = $this->lowering->form($mode);

        return self::ACCESS[$form->signature] ?? throw ImplementationGap::production($form);
    }

    /**
     * Lowers ACTIVE or INACTIVE: a node of `undo_tablespace_state`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function active(Node $state): bool
    {
        $form = $this->lowering->form($state);

        return match ($form->signature) {
            'undo_tablespace_state: ACTIVE_SYM' => true,
            'undo_tablespace_state: INACTIVE_SYM' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers an option list.
     *
     * @return list<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $list): array
    {
        return (new StorageOptionRule($this->lowering))->options($list);
    }
}
