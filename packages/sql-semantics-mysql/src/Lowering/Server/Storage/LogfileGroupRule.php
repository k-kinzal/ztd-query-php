<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Storage;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\LogFile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\LogFileKind;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the log file group statements.
 *
 * Rule: MYSQL-LOGFILE-GROUP-001. Scope: the LOGFILE GROUP alternatives of
 * create, alter and drop, logfile_group_info, alter_logfile_group_info,
 * add_log_file, lg_redofile, logfile_group_name (5.6, 5.7),
 * alter_logfile_stmt, drop_logfile_stmt (8.0 and later), lg_undofile. The
 * names go through MYSQL-TABLESPACE-001 and the options through
 * MYSQL-STORAGE-OPTION-001. Constructs: CreateLogfileGroup,
 * AlterLogfileGroup, DropLogfileGroup, LogFile. Terminates: every child is
 * a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-logfile-group.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-logfile-group.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-logfile-group.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class LogfileGroupRule
{
    /**
     * The productions of a statement: whether it creates, the positions of the name, of the log file and of the options.
     */
    private const STATEMENTS = [
        'logfile_group_info: logfile_group_name add_log_file logfile_group_option_list' => [true, 0, 1, 2],
        'alter_logfile_group_info: logfile_group_name add_log_file alter_logfile_group_option_list' => [false, 0, 1, 2],
        'create: CREATE LOGFILE_SYM GROUP_SYM ident ADD lg_undofile opt_logfile_group_options' => [true, 3, 5, 6],
        'alter_logfile_stmt: ALTER LOGFILE_SYM GROUP_SYM ident ADD lg_undofile opt_alter_logfile_group_options' => [false, 3, 5, 6],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a log file group statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $tablespaces = new TablespaceRule($this->lowering);
        if (isset(self::STATEMENTS[$form->signature])) {
            [$create, $name, $file, $options] = self::STATEMENTS[$form->signature];
            $group = $tablespaces->name($form->node($name));
            $log = $this->file($form->node($file));
            $lowered = $tablespaces->options($form->node($options));

            return $create ? new CreateLogfileGroup($group, $log, $lowered) : new AlterLogfileGroup($group, $log, $lowered);
        }

        return match ($form->signature) {
            'create: CREATE LOGFILE_SYM GROUP_SYM logfile_group_info', 'alter: ALTER LOGFILE_SYM GROUP_SYM alter_logfile_group_info' => $this->statement($this->lowering->form($form->node(3))),
            'drop: DROP LOGFILE_SYM GROUP_SYM logfile_group_name drop_ts_options_list', 'drop_logfile_stmt: DROP LOGFILE_SYM GROUP_SYM ident opt_drop_ts_options' => new DropLogfileGroup(
                $tablespaces->name($form->node(3)),
                $tablespaces->options($form->node(4)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a log file: a node of `add_log_file`, `lg_undofile` or `lg_redofile`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function file(Node $file): LogFile
    {
        $form = $this->lowering->form($file);

        return match ($form->signature) {
            'add_log_file: ADD lg_undofile', 'add_log_file: ADD lg_redofile' => $this->file($form->node(1)),
            'lg_undofile: UNDOFILE_SYM TEXT_STRING_sys' => new LogFile(LogFileKind::Undo, $this->lowering->literals->text($form->node(1))),
            'lg_redofile: REDOFILE_SYM TEXT_STRING_sys' => new LogFile(LogFileKind::Redo, $this->lowering->literals->text($form->node(1))),
            default => throw ImplementationGap::production($form),
        };
    }
}
