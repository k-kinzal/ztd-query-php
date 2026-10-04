<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Utility\Show;

use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateEvent;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateFunction;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTriggers;
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
 * Lowers the SHOW statements of MySQL 5.6 and 5.7 about schema objects, stored programs and the server.
 *
 * Rule: MYSQL-SHOW-LEGACY-OBJECT-LOWERING-001. Scope: the show_param
 * productions MYSQL-SHOW-LEGACY-LOWERING-001 does not lower, and
 * show_engine_param. Terminates: every part is a strict subtree.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/show.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Utility
 */
final class LegacyObjectRule
{
    /**
     * The productions about schema objects, by command.
     */
    private const SCHEMA = [
        'show_param: DATABASES wild_and_where' => 'databases', 'show_param: DATABASES opt_wild_or_where' => 'databases',
        'show_param: opt_full TABLES opt_db wild_and_where' => 'tables', 'show_param: opt_full TABLES opt_db opt_wild_or_where' => 'tables',
        'show_param: opt_full TRIGGERS_SYM opt_db wild_and_where' => 'triggers', 'show_param: opt_full TRIGGERS_SYM opt_db opt_wild_or_where' => 'triggers',
        'show_param: EVENTS_SYM opt_db wild_and_where' => 'events', 'show_param: EVENTS_SYM opt_db opt_wild_or_where' => 'events',
        'show_param: TABLE_SYM STATUS_SYM opt_db wild_and_where' => 'tableStatus', 'show_param: TABLE_SYM STATUS_SYM opt_db opt_wild_or_where' => 'tableStatus',
        'show_param: OPEN_SYM TABLES opt_db wild_and_where' => 'openTables', 'show_param: OPEN_SYM TABLES opt_db opt_wild_or_where' => 'openTables',
        'show_param: opt_full COLUMNS from_or_in table_ident opt_db wild_and_where' => 'columns',
        'show_param: opt_full COLUMNS from_or_in table_ident opt_db opt_wild_or_where' => 'columns',
        'show_param: keys_or_index from_or_in table_ident opt_db where_clause' => 'keys', 'show_param: keys_or_index from_or_in table_ident opt_db opt_where_clause' => 'keys',
        'show_param: CREATE DATABASE opt_if_not_exists ident' => 'createDatabase', 'show_param: CREATE TABLE_SYM table_ident' => 'createTable',
        'show_param: CREATE VIEW_SYM table_ident' => 'createView', 'show_param: CREATE PROCEDURE_SYM sp_name' => 'createProcedure',
        'show_param: CREATE FUNCTION_SYM sp_name' => 'createFunction', 'show_param: CREATE TRIGGER_SYM sp_name' => 'createTrigger',
        'show_param: CREATE EVENT_SYM sp_name' => 'createEvent', 'show_param: PROCEDURE_SYM CODE_SYM sp_name' => 'procedureCode',
        'show_param: FUNCTION_SYM CODE_SYM sp_name' => 'functionCode',
    ];

    /**
     * The productions about the server, by command.
     */
    private const SERVER = [
        'show_param: PLUGINS_SYM' => 'plugins', 'show_param: ENGINE_SYM known_storage_engines show_engine_param' => 'engine',
        'show_param: ENGINE_SYM ALL show_engine_param' => 'engine', 'show_param: opt_storage ENGINES_SYM' => 'engines', 'show_param: PRIVILEGES' => 'privileges',
        'show_param: opt_var_type STATUS_SYM wild_and_where' => 'status', 'show_param: opt_var_type STATUS_SYM opt_wild_or_where_for_show' => 'status',
        'show_param: opt_var_type VARIABLES wild_and_where' => 'variables', 'show_param: opt_var_type VARIABLES opt_wild_or_where_for_show' => 'variables',
        'show_param: opt_full PROCESSLIST_SYM' => 'processlist', 'show_param: charset wild_and_where' => 'charset', 'show_param: charset opt_wild_or_where' => 'charset',
        'show_param: COLLATION_SYM wild_and_where' => 'collation', 'show_param: COLLATION_SYM opt_wild_or_where' => 'collation', 'show_param: GRANTS' => 'grants',
        'show_param: GRANTS FOR_SYM user' => 'grantsFor', 'show_param: CREATE USER clear_privileges user' => 'createUser',
        'show_param: PROCEDURE_SYM STATUS_SYM wild_and_where' => 'procedureStatus', 'show_param: PROCEDURE_SYM STATUS_SYM opt_wild_or_where' => 'procedureStatus',
        'show_param: FUNCTION_SYM STATUS_SYM wild_and_where' => 'functionStatus', 'show_param: FUNCTION_SYM STATUS_SYM opt_wild_or_where' => 'functionStatus',
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a production of `show_param` about schema objects or the server.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        if (isset(self::SCHEMA[$form->signature])) {
            return $this->schema(self::SCHEMA[$form->signature], $form);
        }

        return $this->server(self::SERVER[$form->signature] ?? throw ImplementationGap::production($form), $form);
    }

    /**
     * Lowers a statement about schema objects of a kind.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function schema(string $kind, Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);
        $names = $this->lowering->names;

        return match ($kind) {
            'databases' => new ShowDatabases($clauses->filter($form->node(1))),
            'tables' => new ShowTables($clauses->listing($form->node(0)), $clauses->database($form->node(2)), $clauses->filter($form->node(3))),
            'triggers' => new ShowTriggers($clauses->listing($form->node(0)) !== null, $clauses->database($form->node(2)), $clauses->filter($form->node(3))),
            'events' => new ShowEvents($clauses->database($form->node(1)), $clauses->filter($form->node(2))),
            'tableStatus' => new ShowTableStatus($clauses->database($form->node(2)), $clauses->filter($form->node(3))),
            'openTables' => new ShowOpenTables($clauses->database($form->node(2)), $clauses->filter($form->node(3))),
            'columns' => $this->columns($form),
            'keys' => $this->keys($form),
            'createDatabase' => new ShowCreateDatabase($names->identifier($form->node(3)), $this->lowering->options->present($form->node(2))),
            'createTable' => new ShowCreateTable(new InspectedTable($names->qualified($form->node(2)))),
            'createView' => new ShowCreateView(new InspectedTable($names->qualified($form->node(2)))),
            'createProcedure' => new ShowCreateProcedure($names->qualified($form->node(2))),
            'createFunction' => new ShowCreateFunction($names->qualified($form->node(2))),
            'createTrigger' => new ShowCreateTrigger($names->qualified($form->node(2))),
            'createEvent' => new ShowCreateEvent($names->qualified($form->node(2))),
            'procedureCode' => new ShowProcedureCode($names->qualified($form->node(2))),
            'functionCode' => new ShowFunctionCode($names->qualified($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers SHOW COLUMNS.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function columns(Form $form): ShowColumns
    {
        $clauses = new ClauseRule($this->lowering);
        $clauses->preposition($form->node(2));

        return new ShowColumns(new InspectedTable($this->lowering->names->qualified($form->node(3))), $clauses->listing($form->node(0)), $clauses->database($form->node(4)), $clauses->filter($form->node(5)));
    }

    /**
     * Lowers SHOW INDEX.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function keys(Form $form): ShowKeys
    {
        $clauses = new ClauseRule($this->lowering);
        $this->lowering->options->skip($form->node(0));
        $clauses->preposition($form->node(1));

        return new ShowKeys(new InspectedTable($this->lowering->names->qualified($form->node(2))), false, $clauses->database($form->node(3)), $clauses->where($form->node(4)));
    }

    /**
     * Lowers a statement about the server of a kind.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function server(string $kind, Form $form): Statement
    {
        $clauses = new ClauseRule($this->lowering);
        if ($kind === 'charset') {
            $this->lowering->options->skip($form->node(0));
        }

        return match ($kind) {
            'plugins' => new ShowPlugins(),
            'engine' => $this->engine($form),
            'engines' => $this->engines($form),
            'privileges' => new ShowPrivileges(),
            'status' => new ShowStatus($clauses->scope($form->node(0)), $clauses->filter($form->node(2))),
            'variables' => new ShowVariables($clauses->scope($form->node(0)), $clauses->filter($form->node(2))),
            'processlist' => new ShowProcesslist($clauses->listing($form->node(0)) !== null),
            'charset' => new ShowCharacterSet($clauses->filter($form->node(1))),
            'collation' => new ShowCollation($clauses->filter($form->node(1))),
            'grants' => new ShowGrants(),
            'grantsFor' => new ShowGrants($this->lowering->users->account($form->node(2))),
            'createUser' => $this->createUser($form),
            'procedureStatus' => new ShowProcedureStatus($clauses->filter($form->node(2))),
            'functionStatus' => new ShowFunctionStatus($clauses->filter($form->node(2))),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers SHOW ENGINES, whose STORAGE is an optional word.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function engines(Form $form): ShowEngineCatalog
    {
        $this->lowering->options->present($form->node(0));

        return new ShowEngineCatalog();
    }

    /**
     * Lowers SHOW CREATE USER of MySQL 5.7.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function createUser(Form $form): ShowCreateUser
    {
        $this->lowering->options->skip($form->node(2));

        return new ShowCreateUser($this->lowering->users->account($form->node(3)));
    }

    /**
     * Lowers SHOW ENGINE of MySQL 5.x: the engine, or ALL, and the report of `show_engine_param`.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function engine(Form $form): Statement
    {
        $engine = $form->signature === 'show_param: ENGINE_SYM ALL show_engine_param' ? null : $this->lowering->names->identifier($form->node(1));
        $report = $this->lowering->form($form->node(2));

        return match ($report->signature) {
            'show_engine_param: STATUS_SYM' => new ShowEngineStatus($engine),
            'show_engine_param: MUTEX_SYM' => new ShowEngineMutex($engine),
            'show_engine_param: LOGS_SYM' => new ShowEngineLogs($engine),
            default => throw ImplementationGap::production($report),
        };
    }
}
