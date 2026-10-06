<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\AlterInstance;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneInstance;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\CloneLocal;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\InstanceAction;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Kill;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\KillScope;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RedoLogSwitch;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadKeyring;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Restart;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey;
use SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown;
use SqlSemantics\Statement\Statement;

/**
 * Lowers KILL, SHUTDOWN, RESTART, CLONE and ALTER INSTANCE.
 *
 * Rule: MYSQL-INSTANCE-001. Scope: kill, kill_option, shutdown_stmt,
 * restart_server_stmt, clone_stmt, opt_datadir_ssl, opt_ssl,
 * alter_instance_stmt, alter_instance_action. The KILL identifier goes
 * through the expression family. The equals sign of DATA DIRECTORY is
 * optional (LeafNoise). The server raises a syntax error in the grammar
 * actions, rejected as such here, when a space surrounds the colon between
 * the CLONE account and port, when ROTATE names another key than INNODB
 * (or, from MySQL 8.0, BINLOG), and when ENABLE or DISABLE names other than
 * INNODB REDO_LOG. Constructs: Kill, Shutdown, Restart, CloneLocal,
 * CloneInstance, AlterInstance with RotateMasterKey, ReloadTls,
 * RedoLogSwitch, ReloadKeyring. Terminates: every child is a strict
 * subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html,
 * https://dev.mysql.com/doc/refman/8.4/en/clone.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class InstanceRule
{
    /**
     * The RELOAD TLS productions: the position of the channel name and whether NO ROLLBACK ON ERROR is written.
     */
    private const RELOADS = [
        'alter_instance_action: RELOAD TLS_SYM' => [null, false], 'alter_instance_action: RELOAD TLS_SYM NO_SYM ROLLBACK_SYM ON_SYM ERROR_SYM' => [null, true],
        'alter_instance_action: RELOAD TLS_SYM FOR_SYM CHANNEL_SYM ident' => [4, false],
        'alter_instance_action: RELOAD TLS_SYM FOR_SYM CHANNEL_SYM ident NO_SYM ROLLBACK_SYM ON_SYM ERROR_SYM' => [4, true],
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers an instance statement.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the server rejects the statement while it parses it
     */
    public function statement(Form $form): Statement
    {
        return match ($form->signature) {
            'kill: KILL_SYM kill_option expr' => new Kill($this->scope($form->node(1)), $this->lowering->expressions->expression($form->node(2))),
            'shutdown_stmt: SHUTDOWN' => new Shutdown(),
            'restart_server_stmt: RESTART_SYM' => new Restart(),
            'clone_stmt: CLONE_SYM LOCAL_SYM DATA_SYM DIRECTORY_SYM opt_equal TEXT_STRING_filesystem' => $this->local($form),
            'clone_stmt: CLONE_SYM INSTANCE_SYM FROM user : ulong_num IDENTIFIED_SYM BY TEXT_STRING_sys opt_datadir_ssl' => $this->remote($form),
            'alter_instance_stmt: ALTER INSTANCE_SYM alter_instance_action' => new AlterInstance($this->action($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the modifier of KILL: a node of `kill_option`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function scope(Node $option): ?KillScope
    {
        $form = $this->lowering->form($option);

        return match ($form->signature) {
            'kill_option:' => null,
            'kill_option: CONNECTION_SYM' => KillScope::Connection,
            'kill_option: QUERY_SYM' => KillScope::Query,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CLONE LOCAL DATA DIRECTORY.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function local(Form $form): CloneLocal
    {
        $this->lowering->options->present($form->node(4));

        return new CloneLocal($this->lowering->literals->text($form->node(5)));
    }

    /**
     * Lowers CLONE INSTANCE FROM.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When a space surrounds the colon before the port
     */
    public function remote(Form $form): CloneInstance
    {
        $port = $form->node(5)->tokens();
        if ($form->token(4)->leading !== '' || ($port[0] ?? null)?->leading !== '') {
            throw new AnalysisException('Syntax error: CLONE INSTANCE writes the account, the colon and the port without spaces.');
        }
        $donor = $this->lowering->users->account($form->node(3));
        $number = $this->lowering->numbers->numeral($form->node(5));
        $password = $this->lowering->literals->text($form->node(8));
        $options = $this->lowering->form($form->node(9));
        [$directory, $ssl] = match ($options->signature) {
            'opt_datadir_ssl: opt_ssl' => [null, $this->ssl($options->node(0))],
            'opt_datadir_ssl: DATA_SYM DIRECTORY_SYM opt_equal TEXT_STRING_filesystem opt_ssl' => $this->directory($options),
            default => throw ImplementationGap::production($options),
        };

        return new CloneInstance($donor, $number, $password, $directory, $ssl);
    }

    /**
     * Lowers the data directory and the SSL choice of CLONE INSTANCE.
     *
     * @return array{Text, bool|null}
     * @throws ImplementationGap When a production has no rule
     */
    public function directory(Form $form): array
    {
        $this->lowering->options->present($form->node(2));

        return [$this->lowering->literals->text($form->node(3)), $this->ssl($form->node(4))];
    }

    /**
     * Lowers REQUIRE [NO] SSL: a node of `opt_ssl`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function ssl(Node $ssl): ?bool
    {
        $form = $this->lowering->form($ssl);

        return match ($form->signature) {
            'opt_ssl:' => null,
            'opt_ssl: REQUIRE_SYM SSL_SYM' => true,
            'opt_ssl: REQUIRE_SYM NO_SYM SSL_SYM' => false,
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers the action of ALTER INSTANCE: a node of `alter_instance_action`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When a name is not the one the server expects
     */
    public function action(Node $action): InstanceAction
    {
        $form = $this->lowering->form($action);
        if (isset(self::RELOADS[$form->signature])) {
            [$channel, $noRollback] = self::RELOADS[$form->signature];

            return new ReloadTls($channel === null ? null : $this->lowering->names->identifier($form->node($channel)), $noRollback);
        }

        return match ($form->signature) {
            'alter_instance_action: ROTATE_SYM ident_or_text MASTER_SYM KEY_SYM' => $this->rotate($form->node(1)),
            'alter_instance_action: ENABLE_SYM ident ident' => $this->redo(true, $form),
            'alter_instance_action: DISABLE_SYM ident ident' => $this->redo(false, $form),
            'alter_instance_action: RELOAD KEYRING_SYM' => new ReloadKeyring(),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers ROTATE … MASTER KEY from its name: a node of `ident_or_text`.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the name is not one the release accepts
     */
    public function rotate(Node $name): RotateMasterKey
    {
        $keyring = $this->lowering->names->identifier($name);
        $accepted = $this->lowering->profile->grammar === GrammarRelease::MySql5744 ? ['INNODB'] : ['INNODB', 'BINLOG'];
        if (!in_array(strtoupper($keyring->value), $accepted, true)) {
            throw new AnalysisException('Syntax error: ALTER INSTANCE ROTATE names ' . implode(' or ', $accepted) . ', not ' . $keyring->value . '.');
        }

        return new RotateMasterKey($keyring);
    }

    /**
     * Lowers ENABLE or DISABLE INNODB REDO_LOG.
     *
     * @throws ImplementationGap When a production has no rule
     * @throws AnalysisException When the names are not INNODB REDO_LOG
     */
    public function redo(bool $enable, Form $form): RedoLogSwitch
    {
        $engine = $this->lowering->names->identifier($form->node(1));
        $log = $this->lowering->names->identifier($form->node(2));
        if (strtoupper($engine->value) !== 'INNODB' || strtoupper($log->value) !== 'REDO_LOG') {
            throw new AnalysisException('Syntax error: ALTER INSTANCE ' . ($enable ? 'ENABLE' : 'DISABLE') . ' names INNODB REDO_LOG.');
        }

        return new RedoLogSwitch($enable, $engine, $log);
    }
}
