<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Show;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCreateUser;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineLogs;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPlugins;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPrivileges;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowVariables;
use SqlSemantics\Statement\Statement;

/**
 * Lowers the SHOW statements of MySQL 8.0 and later about the server, the session and accounts.
 *
 * Rule: MYSQL-SHOW-SERVER-LOWERING-001. Scope: show_plugins_stmt,
 * show_engine_logs_stmt, show_engine_mutex_stmt, show_engine_status_stmt,
 * show_engines_stmt, show_status_stmt, show_variables_stmt,
 * show_processlist_stmt, show_character_set_stmt, show_collation_stmt,
 * show_privileges_stmt, show_grants_stmt, show_create_user_stmt.
 * Terminates: every part is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class ServerRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a node of a SHOW rule about the server.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);
        $users = $this->lowering->users;
        switch ($form->signature) {
            case 'show_plugins_stmt: SHOW PLUGINS_SYM':
                return new ShowPlugins();
            case 'show_engine_logs_stmt: SHOW ENGINE_SYM engine_or_all LOGS_SYM':
                return new ShowEngineLogs($clauses->engine($form->node(2)));
            case 'show_engine_mutex_stmt: SHOW ENGINE_SYM engine_or_all MUTEX_SYM':
                return new ShowEngineMutex($clauses->engine($form->node(2)));
            case 'show_engine_status_stmt: SHOW ENGINE_SYM engine_or_all STATUS_SYM':
                return new ShowEngineStatus($clauses->engine($form->node(2)));
            case 'show_engines_stmt: SHOW opt_storage ENGINES_SYM':
                $this->lowering->options->present($form->node(1));

                return new ShowEngineCatalog();
            case 'show_status_stmt: SHOW opt_var_type STATUS_SYM opt_wild_or_where':
                return new ShowStatus($clauses->scope($form->node(1)), $clauses->filter($form->node(3)));
            case 'show_variables_stmt: SHOW opt_var_type VARIABLES opt_wild_or_where':
                return new ShowVariables($clauses->scope($form->node(1)), $clauses->filter($form->node(3)));
            case 'show_processlist_stmt: SHOW opt_full PROCESSLIST_SYM':
                return new ShowProcesslist($clauses->listing($form->node(1)) !== null);
            case 'show_character_set_stmt: SHOW character_set opt_wild_or_where':
                $this->lowering->options->skip($form->node(1));

                return new ShowCharacterSet($clauses->filter($form->node(2)));
            case 'show_collation_stmt: SHOW COLLATION_SYM opt_wild_or_where':
                return new ShowCollation($clauses->filter($form->node(2)));
            case 'show_privileges_stmt: SHOW PRIVILEGES':
                return new ShowPrivileges();
            case 'show_grants_stmt: SHOW GRANTS':
                return new ShowGrants();
            case 'show_grants_stmt: SHOW GRANTS FOR_SYM user':
                return new ShowGrants($users->account($form->node(3)));
            case 'show_grants_stmt: SHOW GRANTS FOR_SYM user USING user_list':
                return new ShowGrants($users->account($form->node(3)), $users->accounts($form->node(5)));
            case 'show_create_user_stmt: SHOW CREATE USER user':
                return new ShowCreateUser($users->account($form->node(3)));
            default:
                throw ImplementationGap::production($form);
        }
    }
}
