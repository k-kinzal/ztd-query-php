<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\DatabaseRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\ForeignServerRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\InstanceRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\PluginRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Instance\SpatialRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\FlushRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\KeyCacheRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Maintenance\MaintenanceRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\LogfileGroupRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Storage\TablespaceRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\LockRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\TransactionRule;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\XaRule;
use SqlSemantics\Statement\Statement;

/**
 * Hands a statement rule of the server family to the rule class of its area.
 *
 * Rule: MYSQL-SERVER-STATEMENT-001. Scope: the statement rules that
 * MYSQL-STATEMENT-ROUTES-001 routes to the server family. Each rule name
 * belongs to one area; the area's rule class reads the production.
 * Terminates: one lookup.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/sql-server-administration-statements.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class StatementRule
{
    /**
     * The statement rules, by the area that lowers them.
     */
    private const AREAS = [
        'begin' => 'transaction', 'begin_stmt' => 'transaction', 'start' => 'transaction', 'commit' => 'transaction', 'rollback' => 'transaction',
        'savepoint' => 'transaction', 'release' => 'transaction', 'xa' => 'xa', 'lock' => 'lock', 'unlock' => 'lock',
        'analyze' => 'maintenance', 'analyze_table_stmt' => 'maintenance', 'check' => 'maintenance', 'check_table_stmt' => 'maintenance',
        'checksum' => 'maintenance', 'optimize' => 'maintenance', 'optimize_table_stmt' => 'maintenance', 'repair' => 'maintenance',
        'repair_table_stmt' => 'maintenance', 'keycache' => 'cache', 'keycache_stmt' => 'cache', 'preload' => 'cache', 'preload_stmt' => 'cache',
        'flush' => 'flush', 'kill' => 'instance', 'shutdown_stmt' => 'instance', 'restart_server_stmt' => 'instance', 'clone_stmt' => 'instance',
        'alter_instance_stmt' => 'instance', 'install' => 'plugin', 'install_stmt' => 'plugin', 'uninstall' => 'plugin', 'create_srs_stmt' => 'spatial',
        'drop_srs_stmt' => 'spatial', 'alter_database_stmt' => 'database', 'drop_database_stmt' => 'database', 'alter_server_stmt' => 'server',
        'drop_server_stmt' => 'server', 'alter_tablespace_stmt' => 'tablespace', 'alter_undo_tablespace_stmt' => 'tablespace',
        'drop_tablespace_stmt' => 'tablespace', 'drop_undo_tablespace_stmt' => 'tablespace', 'alter_logfile_stmt' => 'logfile', 'drop_logfile_stmt' => 'logfile',
    ];

    /**
     * The areas of the `create`, `alter` and `drop` productions routed to the server family, by the symbol after the verb.
     */
    private const DEFINITIONS = [
        'DATABASE' => 'database', 'LOGFILE_SYM' => 'logfile', 'TABLESPACE' => 'tablespace', 'TABLESPACE_SYM' => 'tablespace', 'UNDO_SYM' => 'tablespace',
        'server_def' => 'server', 'SERVER_SYM' => 'server',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a statement node of the server family.
     *
     * @throws ImplementationGap When the rule or a production has no rule
     * @throws AnalysisException When the server rejects the statement while it parses it
     */
    public function statement(Node $statement): Statement
    {
        $form = $this->lowering->form($statement);

        return $this->area(self::AREAS[$statement->name] ?? throw ImplementationGap::production($form), $form);
    }

    /**
     * Lowers a production of `create`, `alter` or `drop` routed to the server family.
     *
     * @throws ImplementationGap When the production has no rule
     * @throws AnalysisException When the server rejects the statement while it parses it
     */
    public function definition(Form $form): Statement
    {
        if ($form->signature === 'alter: alter_instance_stmt') {
            return $this->statement($form->node(0));
        }
        $symbols = explode(' ', $form->signature);

        return $this->area(self::DEFINITIONS[$symbols[2] ?? ''] ?? throw ImplementationGap::production($form), $form);
    }

    /**
     * Lowers a production through the rule class of an area.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the server rejects the statement while it parses it
     */
    public function area(string $area, Form $form): Statement
    {
        return match ($area) {
            'transaction' => (new TransactionRule($this->lowering))->statement($form),
            'xa' => (new XaRule($this->lowering))->statement($form),
            'lock' => (new LockRule($this->lowering))->statement($form),
            'maintenance' => (new MaintenanceRule($this->lowering))->statement($form),
            'cache' => (new KeyCacheRule($this->lowering))->statement($form),
            'flush' => (new FlushRule($this->lowering))->statement($form),
            'instance' => (new InstanceRule($this->lowering))->statement($form),
            'plugin' => (new PluginRule($this->lowering))->statement($form),
            'spatial' => (new SpatialRule($this->lowering))->statement($form),
            'database' => (new DatabaseRule($this->lowering))->statement($form),
            'server' => (new ForeignServerRule($this->lowering))->statement($form),
            'tablespace' => (new TablespaceRule($this->lowering))->statement($form),
            'logfile' => (new LogfileGroupRule($this->lowering))->statement($form),
            default => throw ImplementationGap::production($form),
        };
    }
}
